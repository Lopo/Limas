<?php

namespace Limas\Service;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Entity\Part;
use Limas\Entity\PartCategory;
use Limas\Entity\PartMeasurementUnit;
use Limas\Entity\StatisticSnapshot;
use Limas\Entity\StatisticSnapshotUnit;


class StatisticService
{
	public function __construct(private readonly EntityManagerInterface $entityManager)
	{
	}

	public function getPartCount(bool $withoutPrice = false): int
	{
		$qb = $this->entityManager->createQueryBuilder();
		$qb->select($qb->expr()->count('p'))
			->from(Part::class, 'p');
		if ($withoutPrice) {
			$qb->andWhere($qb->expr()->gt('p.averagePrice', 0));
		}

		return $qb->getQuery()->getSingleScalarResult();
	}

	public function getPartCategoryCount(): int
	{
		$qb = $this->entityManager->createQueryBuilder();
		return $qb->select($qb->expr()->count('c'))
			->from(PartCategory::class, 'c')
			->getQuery()->getSingleScalarResult();
	}

	public function getTotalPrice(): float
	{
		$qb = $this->entityManager->createQueryBuilder();
		return $qb->select('SUM(p.averagePrice * p.stockLevel)')
				->from(Part::class, 'p')
				->getQuery()->getSingleScalarResult() ?? 0;
	}

	public function getAveragePrice(): float
	{
		$qb = $this->entityManager->createQueryBuilder();
		return $qb->select($qb->expr()->avg('p.averagePrice'))
				->from(Part::class, 'p')
				->getQuery()->getSingleScalarResult() ?? 0;
	}

	public function getUnitCounts(): array
	{
		$qb = $this->entityManager->getRepository(PartMeasurementUnit::class)->createQueryBuilder('pu');
		return $qb->select(['pu AS partMeasurementUnit', 'SUM(p.stockLevel) AS stockLevel'])
			->leftJoin('pu.parts', 'p')
			->groupBy('pu.id')
			->getQuery()->getArrayResult();
	}

	public function getStatisticRange()
	{
		return $this->entityManager->getRepository(StatisticSnapshot::class)->createQueryBuilder('sts')
			->select(['MIN(sts.dateTime) AS startDate', 'MAX(sts.dateTime) AS endDate'])
			->getQuery()->getSingleResult();
	}

	public function getSampledStatistics(\DateTime $startDate, \DateTime $endDate, int $sampleSize = 25): array
	{
		if ($startDate->getTimestamp() > $endDate->getTimestamp()) {
			// Swap both times
			list($startDate, $endDate) = [$endDate, $startDate];
		}

		$intervalSize = (int)(($endDate->getTimestamp() - $startDate->getTimestamp()) / $sampleSize);

		$queryStartTime = clone $startDate;
		$queryEndTime = clone $startDate;
		$queryEndTime->add(new \DateInterval('PT' . $intervalSize . 'S'));

		$aPartUnits = $this->entityManager->getRepository(PartMeasurementUnit::class)->findAll();

		$eb = $this->entityManager->getExpressionBuilder();

		$mainQuery = $this->entityManager->getRepository(StatisticSnapshot::class)->createQueryBuilder('sts')
			->select(['AVG(sts.parts) AS parts', 'AVG(sts.categories) AS categories'])
			->andWhere($eb->gte('sts.dateTime', ':start'))
			->andWhere($eb->lte('sts.dateTime', ':end'))
			->getQuery();

		$subQuery = $this->entityManager->getRepository(StatisticSnapshotUnit::class)->createQueryBuilder('stsu')
			->select('AVG(stsu.stockLevel)')
			->join('stsu.statisticSnapshot', 'sts')
			->andWhere($eb->gte('sts.dateTime', ':start'))
			->andWhere($eb->lte('sts.dateTime', ':end'))
			->andWhere($eb->eq('stsu.partUnit', ':partUnit'))
			->getQuery();

		$aRecords = [];

		for ($i = 0; $i < $sampleSize; $i++) {
			$mainQuery->setParameter('start', $queryStartTime);
			$mainQuery->setParameter('end', $queryEndTime);

			$record = $mainQuery->getSingleResult();

			if ($record['parts'] !== null) {
				$record['parts'] = (float)$record['parts'];
			}
			if ($record['categories'] !== null) {
				$record['categories'] = (float)$record['categories'];
			}

			foreach ($aPartUnits as $partUnit) {
				$subQuery->setParameter('start', $queryStartTime);
				$subQuery->setParameter('end', $queryEndTime);
				$subQuery->setParameter('partUnit', $partUnit);

				$aResult = $subQuery->getSingleScalarResult();

				$record['units'][$partUnit->getName()] = $aResult !== null ? (float)$aResult : null;
			}

			$record['start'] = $queryStartTime->format('Y-m-d H:i:s');

			if ($record['parts'] !== null) {
				$aRecords[] = $record;
			}

			$queryStartTime->add(new \DateInterval('PT' . $intervalSize . 'S'));
			$queryEndTime->add(new \DateInterval('PT' . $intervalSize . 'S'));
		}

		return $aRecords;
	}

	public function createStatisticSnapshot(): void
	{
		$snapshot = (new StatisticSnapshot)
			->setParts($this->getPartCount())
			->setCategories($this->getPartCategoryCount());

		$partUnitRepository = $this->entityManager->getRepository(PartMeasurementUnit::class);

		foreach ($this->getUnitCounts() as $unitCount) {
			$snapshot->getUnits()[] = (new StatisticSnapshotUnit)
				->setPartUnit($partUnitRepository->find($unitCount['partMeasurementUnit']['id']))
				->setStatisticSnapshot($snapshot)
				->setStockLevel($unitCount['stockLevel'] ?? 0);
		}

		$this->entityManager->persist($snapshot);
		$this->entityManager->flush();
	}
}
