<?php

namespace Limas\Service;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Entity\Part;
use Limas\Entity\PartParameter;
use Limas\Exceptions\NotAMetaPartException;
use Limas\Filter\Filter;


class PartService
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly FilterService          $filterService,
		private readonly array                  $limas,
		private readonly int|bool               $partLimit = false
	)
	{
	}

	public function getPartCount(): int
	{
		$qb = $this->entityManager->createQueryBuilder();
		return $qb->select($qb->expr()->count('p'))->from(Part::class, 'p')->getQuery()->getSingleScalarResult();
	}

	public function isInternalPartNumberUnique(?string $internalPartNumber, ?Part $part = null): bool
	{
		if (!$this->limas['parts']['internalpartnumberunique'] || $internalPartNumber === '') {
			return true;
		}
		$qb = $this->entityManager->getRepository(Part::class)->createQueryBuilder('p');
		$qb->select($qb->expr()->count('p'))
			->andWhere($qb->expr()->eq('p.internalPartNumber', ':internalPartNumber'))
			->setParameter(':internalPartNumber', $internalPartNumber);
		if ($part !== null) {
			$qb->andWhere($qb->expr()->neq('p.id', ':partId'))
				->setParameter(':partId', $part->getId());
		}
		return !$qb->getQuery()->getSingleScalarResult();
	}

	public function checkPartLimit(): bool
	{
		return $this->partLimit !== false
			&& $this->partLimit !== -1
			&& $this->getPartCount() >= $this->partLimit;
	}

	/**
	 * @return Part[]
	 */
	public function getMatchingMetaParts(Part $metaPart): array
	{
		$paramCount = 0;
		$paramPrefix = ':param';
		$results = [];

		if (!$metaPart->isMetaPart()) {
			throw new NotAMetaPartException;
		}

		foreach ($metaPart->getMetaPartParameterCriterias() as $metaPartParameterCriteria) {
			$qb = $this->entityManager->createQueryBuilder();
			$qb->select('p.id AS id')
				->from(PartParameter::class, 'pp')
				->join('pp.part', 'p');

			$filter = (new Filter)
				->setOperator($metaPartParameterCriteria->getOperator())
				->setProperty('name');

			switch ($metaPartParameterCriteria->getValueType()) {
				case PartParameter::VALUE_TYPE_NUMERIC:
					$expr = $this->filterService->getExpressionForFilter($filter, 'pp.normalizedValue', $paramPrefix . $paramCount);
					$qb->setParameter($paramPrefix . $paramCount, $metaPartParameterCriteria->getNormalizedValue());
					$paramCount++;
					break;
				case PartParameter::VALUE_TYPE_STRING:
					$expr = $this->filterService->getExpressionForFilter($filter, 'pp.stringValue', $paramPrefix . $paramCount);
					$qb->setParameter($paramPrefix . $paramCount, $metaPartParameterCriteria->getStringValue());
					$paramCount++;
					break;
				default:
					throw new \InvalidArgumentException('Unknown value type');
			}

			$qb->setParameter($paramPrefix . $paramCount, $metaPartParameterCriteria->getPartParameterName())
				->andWhere($qb->expr()->andX(
					$expr,
					$qb->expr()->eq('pp.name', $paramPrefix . $paramCount)
				));

			$result = [];
			foreach ($qb->getQuery()->getScalarResult() as $partId) {
				$result[] = $partId['id'];
			}
			$results[] = $result;
		}

		if (count($results) > 1) {
			$result = array_intersect(...$results);
		} else {
			$result = count($results) === 1
				? $results[0]
				: [];
		}

		if (count($result) > 0) {
			$qb = $this->entityManager->createQueryBuilder();
			return $qb->select('p')->from(Part::class, 'p')
				->where($qb->expr()->in('p.id', ':result'))
				->setParameter(':result', $result)
				->getQuery()->getResult();
		}
		return [];
	}
}
