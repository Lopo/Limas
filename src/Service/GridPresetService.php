<?php

namespace Limas\Service;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Entity\GridPreset;


class GridPresetService
{
	public function __construct(private readonly EntityManagerInterface $entityManager)
	{
	}

	public function markGridPresetAsDefault(GridPreset $gridPreset): void
	{
		$queryBuilder = $this->entityManager->createQueryBuilder();
		$queryBuilder->update(GridPreset::class, 'gp')
			->set('gp.gridDefault', ':default')
			->where('gp.grid = :grid')
			->setParameter('grid', $gridPreset->getGrid())
			->setParameter('default', false)
			->getQuery()->execute();

		$gridPreset->setGridDefault();
	}

	public function getDefaultPresets(): array
	{
		$queryBuilder = $this->entityManager->createQueryBuilder();
		return $queryBuilder->select('gp.grid', 'gp.configuration')
			->from(GridPreset::class, 'gp')
			->andWhere($queryBuilder->expr()->eq('gp.gridDefault', ':default'))
			->setParameter('default', true)
			->getQuery()->getArrayResult();
	}
}
