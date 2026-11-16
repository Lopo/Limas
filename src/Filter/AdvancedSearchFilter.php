<?php

namespace Limas\Filter;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Exception\ItemNotFoundException;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Comparison;
use Doctrine\ORM\Query\Expr\Composite;
use Doctrine\ORM\Query\Expr\Func;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Limas\Entity\PartParameter;
use Limas\Entity\User;
use Limas\Service\FilterService;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;


class AdvancedSearchFilter
	implements QueryCollectionExtensionInterface
{
	/**
	 * Cap on association-chain depth in a filter/sort property path. Blocks a
	 * caller from forcing arbitrarily deep JOIN chains (a.b.c.d.e…) as query
	 * amplification. Legitimate Limas paths are 1 level (storageLocation.name,
	 * parameters.value); 4 leaves generous headroom.
	 */
	private const int MAX_ASSOCIATION_DEPTH = 4;
	/**
	 * Fields that must never be filtered or sorted on, keyed by entity class.
	 * `viaAssociation` applies when the class is reached through a join;
	 * `root` when it is the queried resource itself (the admin-only user list
	 * may still filter by e-mail).
	 */
	private const array RESTRICTED_FIELDS = [
		User::class => [
			'viaAssociation' => ['password', 'passwordChangedAt', 'newPassword', 'roles', 'lastSeen', 'email'],
			'root' => ['password', 'passwordChangedAt', 'newPassword']
		]
	];

	private array $aliases = [];
	private int $parameterCount = 0;
	private array $joins = [];
	private int $subFilterGroupCount = 0;


	public function __construct(
		private readonly FilterService             $filterService,
		private readonly IriConverterInterface     $iriConverter,
		private readonly PropertyAccessorInterface $propertyAccessor,
		private readonly EntityManagerInterface    $entityManager,
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
	private function getFilterValueFromUrl($value): mixed
	{
		if (is_array($value)) {
			return array_map(fn($item) => $this->getFilterValueFromUrl($item), $value);
		}
		// Only IRI-shaped strings are resolved. Handing arbitrary search text
		// to the IRI converter made Symfony reject values containing quotes
		// or backslashes ("Invalid URI" → 400) before they ever reached the
		// query, so such names were simply unsearchable.
		if (!is_string($value) || !str_starts_with($value, '/')) {
			return $value;
		}
		try {
			return $this->propertyAccessor->getValue($this->iriConverter->getResourceFromIri($value), 'id');
		} catch (\InvalidArgumentException|ItemNotFoundException) {
			// Not a resolvable IRI — use the raw value
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
		// Malformed filter/order input is the client's fault: answer 400, not
		// a 500 from a JSON parse error or an unknown operator
		try {
			$filter = $request->query->get('filter') !== null
				? Json::decode($request->query->get('filter'))
				: null;
			$order = $request->query->get('order') !== null
				? Json::decode($request->query->get('order'))
				: null;
			$properties = $this->extractConfiguration($filter, $order);
		} catch (JsonException|\RuntimeException|\TypeError $e) {
			throw new BadRequestHttpException('Invalid filter or order: ' . $e->getMessage(), $e);
		}

		$this->assertPropertiesAllowed($properties['filters'], $properties['sorters'], $resourceClass);

		$this->filter($queryBuilder, $properties['filters'], $properties['sorters']);
	}

	/**
	 * Reject filter/sort property paths that don't map to a real field or
	 * association on the queried entity, and cap association-chain depth. This
	 * turns an attacker's arbitrary or deeply-nested path — which would
	 * otherwise leak a DQL parse error (existence oracle) or force a huge JOIN
	 * chain (query-amplification DoS) — into a clean 400. Values are already
	 * parameter-bound elsewhere, so this governs *which* columns/relations may
	 * be referenced, not injection.
	 */
	public function assertPropertiesAllowed(array $filters, array $sorters, string $resourceClass): void
	{
		foreach ($filters as $filter) {
			$this->assertFilterAllowed($filter, $resourceClass);
		}

		foreach ($sorters as $sorter) {
			// paramValues.<name> is a synthetic association resolved via a correlated subquery, not a real Doctrine relation — allow it
			if ($sorter->getAssociation() === 'paramValues') {
				continue;
			}
			$this->assertPathExists($resourceClass, $sorter->getAssociation(), $sorter->getProperty());
		}
	}

	private function assertFilterAllowed(Filter $filter, string $resourceClass): void
	{
		if ($filter->hasSubFilters()) {
			foreach ($filter->getSubFilters() as $subFilter) {
				$this->assertFilterAllowed($subFilter, $resourceClass);
			}
			return;
		}

		$this->assertPathExists($resourceClass, $filter->getAssociation(), $filter->getProperty());
	}

	private function assertPathExists(string $resourceClass, ?string $association, string $property): void
	{
		$metadata = $this->entityManager->getClassMetadata($resourceClass);

		if ($association !== null && $association !== '') {
			$segments = explode('.', $association);
			if (count($segments) > self::MAX_ASSOCIATION_DEPTH) {
				throw new BadRequestHttpException(sprintf('Filter association "%s" is nested too deeply.', $association));
			}
			foreach ($segments as $segment) {
				if (!$metadata->hasAssociation($segment)) {
					throw new BadRequestHttpException(sprintf('Unknown filter association "%s".', $association));
				}
				$metadata = $this->entityManager->getClassMetadata($metadata->getAssociationTargetClass($segment));
			}
		}

		// `@id` is the Hydra IRI alias for the identifier
		$field = $property === '@id' ? 'id' : $property;
		if (!$metadata->hasField($field) && !$metadata->hasAssociation($field)) {
			throw new BadRequestHttpException(sprintf('Unknown filter property "%s".', $property));
		}
		$restricted = self::RESTRICTED_FIELDS[$metadata->getName()][$association !== null && $association !== '' ? 'viaAssociation' : 'root'] ?? [];
		if (in_array($field, $restricted, true)) {
			// Same answer as a non-existent field: don't confirm it exists
			throw new BadRequestHttpException(sprintf('Unknown filter property "%s".', $property));
		}
	}
}
