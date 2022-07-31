<?php

namespace Limas\Controller\Actions;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\DataProvider\ItemDataProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Limas\Entity\BatchJob;
use Limas\Filter\AdvancedSearchFilter;
use Limas\Service\ReflectionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\PropertyAccess\PropertyAccess;


#[AsController]
class BatchJobActions
	extends AbstractController
{
	use ActionUtilTrait;


	public function __construct(
		private readonly EntityManagerInterface    $entityManager,
		private readonly AdvancedSearchFilter      $advancedSearchFilter,
		private readonly ReflectionService         $reflectionService,
		private readonly IriConverterInterface     $iriConverter,
		private readonly ItemDataProviderInterface $dataProvider
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
					if ($queryField['property'] == $batchJobQueryField->getProperty()) {
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
					if ($updateField['property'] == $batchJobUpdateField->getProperty()) {
						$updateFieldConfig->value = $updateField['value'];
					}
				}
			}

			$updateFieldConfigs[] = $updateFieldConfig;
		}

		$configuration = $this->advancedSearchFilter->extractConfiguration($queryFilters, []);

		$qb = new QueryBuilder($this->entityManager);
		$qb->select('o')->from($this->reflectionService->convertExtJSToPHPClassName($batchJob->getBaseEntity()), 'o');

		$this->advancedSearchFilter->filter($qb, $configuration['filters'], $configuration['sorters']);

		$data = $qb->getQuery()->getResult();

		$accessor = PropertyAccess::createPropertyAccessor();

		foreach ($data as $item) {
			foreach ($updateFieldConfigs as $updateField) {
				try {
					$value = $this->iriConverter->getItemFromIri($updateField->value);
				} catch (\Exception $e) {
					$value = $updateField->value;
				}

				$accessor->setValue($item, $updateField->property, $value);
			}
		}

		$this->entityManager->flush();

		return [];
	}
}
