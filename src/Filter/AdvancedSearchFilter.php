<?php

namespace Limas\Filter;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Exception\ItemNotFoundException;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\Query\Expr\Comparison;
use Doctrine\ORM\Query\Expr\Composite;
use Doctrine\ORM\Query\Expr\Func;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Limas\Entity\PartParameter;
use Limas\Service\FilterService;
use Nette\Utils\Json;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;


class AdvancedSearchFilter
	implements QueryCollectionExtensionInterface
{
	private array $aliases = [];
	private int $parameterCount = 0;
	private array $joins = [];
	private int $subFilterGroupCount = 0;


	public function __construct(
		private readonly FilterService             $filterService,
		private readonly IriConverterInterface     $iriConverter,
		private readonly PropertyAccessorInterface $propertyAccessor,
		private readonly ?RequestStack             $requestStack = null/*,

		array                                      $properties = null*/
	)
	{
	}

	public function filter(QueryBuilder $queryBuilder, $filters, $sorters): void
	{
		$this->joins = $this->aliases = [];
		$this->parameterCount = 0;
		$this->subFilterGroupCount = 0;

		foreach ($filters as $filter) {
			$queryBuilder->andWhere(
				$this->getFilterExpression($queryBuilder, $filter)
			);
		}

		foreach ($sorters as $sorter) {
			// `paramValues` is a synthetic association — applyOrderByExpression
			// handles it via a correlated subquery and adding a real JOIN here
			// would try to navigate a non-existent Doctrine association
			if ($sorter->getAssociation() !== null && $sorter->getAssociation() !== 'paramValues') {
				$this->addJoins($queryBuilder, $sorter); // Pull in associations
			}

			$this->applyOrderByExpression($queryBuilder, $sorter);
		}
	}

	/**
	 * Gets the ID from an URI or a raw ID
	 */
	private function getFilterValueFromUrl($value): array|string
	{
		if (is_array($value)) {
			$items = [];
			foreach ($value as $iri) {
				try {
					$items[] = $this->propertyAccessor->getValue($this->iriConverter->getResourceFromIri($iri), 'id');
				} catch (\InvalidArgumentException|ItemNotFoundException $e) {
					$items[] = $iri;
				}
			}
			return $items;
		}

		try {
			return $this->propertyAccessor->getValue($this->iriConverter->getResourceFromIri($value), 'id');
		} catch (\InvalidArgumentException|ItemNotFoundException $e) {
			// Do nothing, return the raw value
		}

		return $value;
	}

	/**
	 * Adds all required joins to the queryBuilder
	 */
	private function addJoins(QueryBuilder $queryBuilder, AssociationPropertyInterface $filter): void
	{
		if (in_array($filter->getAssociation(), $this->joins, true)) {
			return; // Association already added, return
		}

		$associations = explode('.', $filter->getAssociation());
		$fullAssociation = 'o';
		foreach ($associations as $key => $association) {
			$parent = $associations[$key - 1] ?? 'o';
			$fullAssociation .= '.' . $association;
			$queryBuilder->join($parent . '.' . $association, $this->getAlias($fullAssociation));
		}

		$this->joins[] = $filter->getAssociation();
	}

	/**
	 * Returns the expression for a specific filter
	 *
	 * @throws \RuntimeException
	 */
	private function getFilterExpression(QueryBuilder $queryBuilder, Filter $filter): Comparison|Func|Composite
	{
		if ($filter->hasSubFilters()) {
			$subFilterExpressions = [];

			// Collect associations used by subfilters in this group
			$groupAssociations = [];
			foreach ($filter->getSubFilters() as $subFilter) {
				if ($subFilter->getAssociation() !== null && !in_array($subFilter->getAssociation(), $groupAssociations, true)) {
					$groupAssociations[] = $subFilter->getAssociation();
				}
			}

			// For associations already joined by a previous group, create new join aliases
			// so each subfilter group gets its own JOIN (e.g. parameters filtered by Diameter
			// and parameters filtered by Capacitance need separate JOINs)
			foreach ($groupAssociations as $assoc) {
				if (in_array($assoc, $this->joins, true)) {
					$this->joins = array_values(array_diff($this->joins, [$assoc]));

					$prefix = 'o.' . $assoc;
					foreach ($this->aliases as $k => $v) {
						if ($k === $prefix || str_starts_with($k, $prefix . '.')) {
							unset($this->aliases[$k]);
							$this->aliases[$k . '#' . $this->subFilterGroupCount] = $v;
						}
					}
					$this->subFilterGroupCount++;
				}
			}

			foreach ($filter->getSubFilters() as $subFilter) {
				if ($subFilter->getAssociation() !== null) {
					$this->addJoins($queryBuilder, $subFilter);
				}

				$subFilterExpressions[] = $this->getFilterExpression($queryBuilder, $subFilter);
			}

			if ($filter->getType() === Filter::TYPE_AND) {
				return call_user_func_array([$queryBuilder->expr(), 'andX'], $subFilterExpressions);
			}
			return call_user_func_array([$queryBuilder->expr(), 'orX'], $subFilterExpressions);
		}

		if ($filter->getAssociation() !== null) {
			$this->addJoins($queryBuilder, $filter);
			$alias = $this->getAlias('o.' . $filter->getAssociation()) . '.' . $filter->getProperty();
		} else {
			$alias = 'o.' . $filter->getProperty();
		}

		if (strtolower($filter->getOperator()) === Filter::OPERATOR_IN) {
			if (!is_array($filter->getValue())) {
				throw new \RuntimeException('Value needs to be an array for the IN operator');
			}
			return $queryBuilder->expr()->in($alias, $filter->getValue());
		}
		$paramName = ':param' . $this->parameterCount;
		$this->parameterCount++;
		$queryBuilder->setParameter($paramName, $filter->getValue());

		return $this->filterService->getExpressionForFilter($filter, $alias, $paramName);
	}

	/**
	 * Returns the expression for a specific sort order.
	 *
	 * Special case `paramValues.<paramName>` (PK #1217 (b)): the FE Param
	 * Renderer columns expose their dataIndex in that shape so the user can
	 * click the header to sort by a specific PartParameter value. Translate
	 * into a LEFT JOIN onto PartParameter filtered by name + ORDER BY on the
	 * normalized numeric value, falling back to stringValue so string-typed
	 * params still sort sensibly. NULL parts (no such parameter row) sort
	 * last in ASC, first in DESC — the DB's natural ordering.
	 */
	private function applyOrderByExpression(QueryBuilder $queryBuilder, Sorter $sorter): QueryBuilder
	{
		if ($sorter->getAssociation() === 'paramValues') {
			$paramName = $sorter->getProperty();
			// Standalone LEFT JOIN onto PartParameter filtered by name. The
			// outer filter() loop skips addJoins() for the 'paramValues'
			// pseudo-association so API Platform's QueryChecker (which would
			// barf on `o.paramValues`) never sees that bogus path. Alias
			// includes parameterCount so multiple sort criteria don't share
			// a parameter binding.
			$alias = 'ppSort_' . preg_replace('/[^A-Za-z0-9_]/', '_', $paramName) . $this->parameterCount;
			$paramKey = $alias . '_name';
			$this->parameterCount++;
			$queryBuilder->leftJoin(
				PartParameter::class,
				$alias,
				Join::WITH,
				$queryBuilder->expr()->andX(
					$queryBuilder->expr()->eq($alias . '.part', 'o'),
					$queryBuilder->expr()->eq($alias . '.name', ':' . $paramKey)
				)
			);
			$queryBuilder->setParameter($paramKey, $paramName);
			// Two ORDER BY clauses — numeric first so rows with real numeric
			// values sort deterministically, stringValue as the tiebreaker
			// for string-typed params (where normalizedValue is NULL)
			$queryBuilder->addOrderBy($alias . '.normalizedValue', $sorter->getDirection());
			$queryBuilder->addOrderBy($alias . '.stringValue', $sorter->getDirection());
			return $queryBuilder;
		}

		// FE columns use `@id` as the dataIndex for the Hydra IRI field —
		// DQL has no `@` token so map it back to the real entity property
		$property = $sorter->getProperty() === '@id' ? 'id' : $sorter->getProperty();
		$alias = $sorter->getAssociation() !== null
			? $this->getAlias('o.' . $sorter->getAssociation()) . '.' . $property
			: 'o.' . $property;

		return $queryBuilder->addOrderBy($alias, $sorter->getDirection());
	}

	public function extractConfiguration(mixed $filterData, mixed $sorterData): array
	{
		$filters = [];

		if (is_array($filterData)) {
			foreach ($filterData as $filter) {
				$filters[] = $this->extractJSONFilters($filter);
			}
		} elseif (is_object($filterData)) {
			$filters[] = $this->extractJSONFilters($filterData);
		}

		$sorters = [];

		if (is_array($sorterData)) {
			foreach ($sorterData as $sorter) {
				$sorters[] = $this->extractJSONSorters($sorter);
			}
		} elseif (is_object($sorterData)) {
			$sorters[] = $this->extractJSONSorters($sorterData);
		}

		return ['filters' => $filters, 'sorters' => $sorters];
	}

	/**
	 * Returns an alias for the given association property
	 *
	 * @param string $property The property in FQDN format, e.g. "comments.authors.name"
	 * @return string The table alias
	 */
	private function getAlias(string $property): string
	{
		if (!array_key_exists($property, $this->aliases)) {
			$this->aliases[$property] = 't' . count($this->aliases);
		}

		return $this->aliases[$property];
	}

	/**
	 * Extracts the filters from the JSON object
	 *
	 * @throws \RuntimeException
	 */
	private function extractJSONFilters($data): Filter
	{
		$filter = new Filter;

		if (property_exists($data, 'property')) {
			if (str_contains($data->property, '.')) {
				$associations = explode('.', $data->property);
				$property = array_pop($associations);

				$filter->setAssociation(implode('.', $associations));
				$filter->setProperty($property);
			} else {
				$filter->setAssociation(null);
				$filter->setProperty($data->property);
			}
		} elseif (property_exists($data, 'subfilters')) {
			if (property_exists($data, 'type')) {
				$filter->setType(strtolower($data->type));
			}

			if (is_array($data->subfilters)) {
				$subfilters = [];
				foreach ($data->subfilters as $subfilter) {
					$subfilters[] = $this->extractJSONFilters($subfilter);
				}
				$filter->setSubFilters($subfilters);

				return $filter;
			}
			throw new \RuntimeException('The subfilters must be an array of objects');
		} else {
			throw new \RuntimeException('You need to set the filter property');
		}

		if (property_exists($data, 'operator')) {
			$filter->setOperator($data->operator);
		} else {
			$filter->setOperator(Filter::OPERATOR_EQUALS);
		}

		if (property_exists($data, 'value')) {
			$filter->setValue($this->getFilterValueFromUrl($data->value));
		} else {
			throw new \RuntimeException('No value specified');
		}

		return $filter;
	}

	/**
	 * Extracts the sorters from the JSON object
	 *
	 * @throws \RuntimeException
	 */
	private function extractJSONSorters($data): Sorter
	{
		$sorter = new Sorter;

		if ($data->property) {
			if (str_contains($data->property, '.')) {
				$associations = explode('.', $data->property);
				$property = array_pop($associations);

				$sorter->setAssociation(implode('.', $associations));
				$sorter->setProperty($property);
			} else {
				$sorter->setAssociation(null);
				$sorter->setProperty($data->property);
			}
		} else {
			throw new \RuntimeException('You need to set the filter property');
		}

		if ($data->direction) {
			switch (strtoupper($data->direction)) {
				case 'DESC':
					$sorter->setDirection('DESC');
					break;
				case 'ASC':
				default:
					$sorter->setDirection('ASC');
					break;
			}
		} else {
			$sorter->setDirection('ASC');
		}

		return $sorter;
	}

	public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
	{
		$request = $this->requestStack->getCurrentRequest();
		if (null === $request) {
			return;
		}
		$filter = $request->query->get('filter') !== null
			? Json::decode($request->query->get('filter'))
			: null;
		$order = $request->query->get('order') !== null
			? Json::decode($request->query->get('order'))
			: null;

		$properties = $this->extractConfiguration($filter, $order);

		$this->filter($queryBuilder, $properties['filters'], $properties['sorters']);
	}
}
