<?php

namespace Limas\Service;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Entity\PartMeasurementUnit;


readonly class PartMeasurementUnitService
{
	public function __construct(private EntityManagerInterface $entityManager)
	{
	}

	public function setDefault(PartMeasurementUnit $partMeasurementUnit): void
	{
		$this->entityManager->beginTransaction();

		$qb = $this->entityManager->createQueryBuilder();
		$qb->update(PartMeasurementUnit::class, 'pu')
			->set('pu.default', ':default')
			->where($qb->expr()->eq('pu.id', ':id'))
			->setParameters([
				'id' => $partMeasurementUnit->getId(),
				'default' => true
			])
			->getQuery()->execute();
		$qb->update(PartMeasurementUnit::class, 'pu')
			->set('pu.default', ':default')
			->where($qb->expr()->neq('pu.id', ':id'))
			->setParameters([
				'id' => $partMeasurementUnit->getId(),
				'default' => false
			])
			->getQuery()->execute();

		$this->entityManager->commit();
	}
}
