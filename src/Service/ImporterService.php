<?php

namespace Limas\Service;

use ApiPlatform\Metadata\IriConverterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Limas\Configuration\EntityConfiguration;
use Limas\Entity\BatchJob;
use Limas\Filter\AdvancedSearchFilter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;


class ImporterService
{
	protected string $baseEntity;
	protected $importConfiguration;
	protected iterable $importData;


	public function __construct(
		private readonly EntityManagerInterface $em,
		private readonly ReflectionService      $reflectionService,
		private readonly AdvancedSearchFilter   $advancedSearchFilter,
		private readonly IriConverterInterface  $iriConverter
	)
	{
	}

	public function setBaseEntity(string $baseEntity): self
	{
		// Same inventory-only allowlist as batch jobs: the importer builds
		// entities of this class and writes their fields via PropertyAccess
		if (!in_array($baseEntity, BatchJob::ALLOWED_BASE_ENTITIES, true)) {
			throw new BadRequestHttpException('Imports can only target inventory entities.');
		}
		$this->baseEntity = $this->reflectionService->convertExtJSToPHPClassName($baseEntity);
		return $this;
	}

	public function setImportConfiguration(mixed $importConfiguration): self
	{
		$this->importConfiguration = $importConfiguration;
		return $this;
	}

	public function setImportData(iterable $importData): self
	{
		$this->importData = $importData;
		return $this;
	}

	public function import(bool $preview = false): array
	{
//		$entities = [];
		$logs = [];

		$configuration = $this->parseConfiguration();

		$this->em->beginTransaction();

		foreach ($this->importData as $row) {
			$this->em->beginTransaction();
//			$entity = $configuration->import($row);

//			if ($entity !== null) {
//				$entities[] = $entity;
//			}
			$logs[] = implode('<br/>', [
				'data' => implode(',', $row),
				'<p style="text-indent: 50px;">',
				'log' => '   ' . implode('<br/>   ', $configuration->getLog()),
				'</p>'
			]);

			$configuration->clearLog();

			foreach ($configuration->getPersistEntities() as $entity) {
				$this->em->persist($entity);
			}
			$this->em->flush();
			$this->em->commit();
		}

		if ($preview) {
			$this->em->rollback();
		} else {
			$this->em->commit();
		}

		return [$configuration->getPersistEntities(), implode('<br/>', $logs)];
	}

	public function parseConfiguration(): EntityConfiguration
	{
		$configuration = new EntityConfiguration(
			$this->em->getClassMetadata($this->baseEntity),
			$this->baseEntity,
			$this->reflectionService,
			$this->em,
			$this->advancedSearchFilter,
			$this->iriConverter
		);
		$configuration->parseConfiguration($this->importConfiguration);

		return $configuration;
	}
}
