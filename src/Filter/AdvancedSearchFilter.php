<?php

namespace Limas\Filter;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Exception\ItemNotFoundException;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\Query\Expr\Comparison;
use Doctrine\ORM\Query\Expr\Composite;
use Doctrine\ORM\Query\Expr\Func;
use Doctrine\ORM\QueryBuilder;
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

		foreach ($filters as $filter) {
			$queryBuilder->andWhere(
				$this->getFilterExpression($queryBuilder, $filter)
			);
		}

		foreach ($sorters as $sorter) {
			if ($sorter->getAssociation() !== null) {
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
	 * Returns the expression for a specific sort order
	 */
	private function applyOrderByExpression(QueryBuilder $queryBuilder, Sorter $sorter): QueryBuilder
	{
		$alias = $sorter->getAssociation() !== null
			? $this->getAlias('o.' . $sorter->getAssociation()) . '.' . $sorter->getProperty()
			: 'o.' . $sorter->getProperty();

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

	public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, Operation $operation = null, array $context = []): void
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
