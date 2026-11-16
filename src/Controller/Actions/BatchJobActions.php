<?php

namespace Limas\Controller\Actions;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Limas\Entity\BatchJob;
use Limas\Filter\AdvancedSearchFilter;
use Limas\Service\ReflectionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Serializer\Attribute\Groups;


#[AsController]
class BatchJobActions
	extends AbstractController
{
	use ActionUtilTrait;


	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly AdvancedSearchFilter   $advancedSearchFilter,
		private readonly ReflectionService      $reflectionService,
		private readonly IriConverterInterface  $iriConverter,
		private readonly ItemProvider           $dataProvider
	)
	{
	}

	public function BatchJobExecute(Request $request, int $id): array
	{
		$batchJob = $this->getItem($this->dataProvider, BatchJob::class, $id);
		$queryFields = $updateFields = [];

		if ($request->request->has('queryFields')) {
			$queryFields = json_decode($request->request->get('queryFields'), true, 512, JSON_THROW_ON_ERROR);
		}
		if ($request->request->has('updateFields')) {
			$updateFields = json_decode($request->request->get('updateFields'), true, 512, JSON_THROW_ON_ERROR);
		}
		$queryFilters = [];

		foreach ($batchJob->getBatchJobQueryFields() as $batchJobQueryField) {
			$queryFilter = new \stdClass;
			$queryFilter->property = $batchJobQueryField->getProperty();
			$queryFilter->operator = $batchJobQueryField->getOperator();
			$queryFilter->value = $batchJobQueryField->getValue();

			if ($batchJobQueryField->getDynamic()) {
				foreach ($queryFields as $queryField) {
					if ($queryField['property'] === $batchJobQueryField->getProperty()) {
						$queryFilter->value = $queryField['value'];
					}
				}
			}

			$queryFilters[] = $queryFilter;
		}

		$updateFieldConfigs = [];

		foreach ($batchJob->getBatchJobUpdateFields() as $batchJobUpdateField) {
			$updateFieldConfig = new \stdClass;
			$updateFieldConfig->property = $batchJobUpdateField->getProperty();
			$updateFieldConfig->value = $batchJobUpdateField->getValue();

			if ($batchJobUpdateField->getDynamic()) {
				foreach ($updateFields as $updateField) {
					if ($updateField['property'] === $batchJobUpdateField->getProperty()) {
						$updateFieldConfig->value = $updateField['value'];
					}
				}
			}

			$updateFieldConfigs[] = $updateFieldConfig;
		}

		// The stored base entity and every field name are user-supplied: only
		// inventory entities may be touched, query fields go through the same
		// allowlist as API filters, and only fields the entity's own API would
		// accept may be updated
		if (!in_array($batchJob->getBaseEntity(), BatchJob::ALLOWED_BASE_ENTITIES, true)) {
			throw new BadRequestHttpException('Batch jobs can only target inventory entities.');
		}
		$entityClass = $this->reflectionService->convertExtJSToPHPClassName($batchJob->getBaseEntity());
		foreach ($updateFieldConfigs as $updateField) {
			$this->assertUpdatableField($entityClass, (string)$updateField->property);
		}

		try {
			$configuration = $this->advancedSearchFilter->extractConfiguration($queryFilters, []);
		} catch (\RuntimeException $e) {
			throw new BadRequestHttpException('Invalid batch job query: ' . $e->getMessage(), $e);
		}
		$this->advancedSearchFilter->assertPropertiesAllowed($configuration['filters'], $configuration['sorters'], $entityClass);

		$qb = new QueryBuilder($this->entityManager);
		$qb->select('o')->from($entityClass, 'o');

		$this->advancedSearchFilter->filter($qb, $configuration['filters'], $configuration['sorters']);

		$data = $qb->getQuery()->getResult();

		$accessor = PropertyAccess::createPropertyAccessor();

		$metadata = $this->entityManager->getClassMetadata($entityClass);
		foreach ($data as $item) {
			foreach ($updateFieldConfigs as $updateField) {
				$property = (string)$updateField->property;
				if ($metadata->hasAssociation($property)) {
					$target = $metadata->getAssociationTargetClass($property);
					$value = $updateField->value === null || $updateField->value === '' ? null : $this->resolveReference((string)$updateField->value, $target);
				} else {
					$value = $updateField->value;
				}
				$accessor->setValue($item, $property, $value);
			}
		}

		$this->entityManager->flush();

		return [];
	}

	/**
	 * A batch update may only set a plain column or a single-valued relation
	 * that the entity's own API accepts on write — i.e. a property carrying
	 * one of the resource's denormalization groups. That keeps out computed
	 * or server-managed fields (e.g. Part::$stockLevel, group `readonly`) and
	 * collections.
	 */
	private function assertUpdatableField(string $entityClass, string $property): void
	{
		$metadata = $this->entityManager->getClassMetadata($entityClass);
		$isColumn = $metadata->hasField($property) && !$metadata->isIdentifier($property);
		$isReference = $metadata->hasAssociation($property) && $metadata->isSingleValuedAssociation($property);
		if (!$isColumn && !$isReference) {
			throw new BadRequestHttpException(sprintf('Field "%s" cannot be updated by a batch job.', $property));
		}

		$writeGroups = [];
		foreach ((new \ReflectionClass($entityClass))->getAttributes(ApiResource::class) as $attribute) {
			$writeGroups = $attribute->newInstance()->getDenormalizationContext()['groups'] ?? [];
		}
		$class = new \ReflectionClass($entityClass);
		while ($class !== false && !$class->hasProperty($property)) {
			$class = $class->getParentClass();
		}
		$propertyGroups = [];
		if ($class !== false) {
			foreach ($class->getProperty($property)->getAttributes(Groups::class) as $attribute) {
				$propertyGroups = [...$propertyGroups, ...$attribute->newInstance()->groups];
			}
		}
		if (array_intersect($propertyGroups, $writeGroups) === []) {
			throw new BadRequestHttpException(sprintf('Field "%s" cannot be updated by a batch job.', $property));
		}
	}

	/**
	 * Resolve an IRI for a relation and make sure it points at the relation's
	 * target class, so a job cannot hang an unrelated record (or a user) on it
	 */
	private function resolveReference(string $iri, string $targetClass): object
	{
		try {
			$resource = $this->iriConverter->getResourceFromIri($iri);
		} catch (\Exception $e) {
			throw new BadRequestHttpException(sprintf('"%s" is not a valid reference.', $iri), $e);
		}
		if (!$resource instanceof $targetClass) {
			throw new BadRequestHttpException(sprintf('"%s" is not a valid reference for this field.', $iri));
		}
		return $resource;
	}
}
