<?php

namespace Limas\Service\Integration\InfoProvider;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Entity\Part;
use Limas\Entity\PartManufacturer;
use Limas\Service\Integration\InfoProvider\Dto\AggregatedPartCandidate;
use Limas\Service\Integration\InfoProvider\Dto\ExistingPartInfo;
use Limas\Service\ManufacturerCanonicalizer;


/**
 * Matches each merged aggregator candidate against the local Part table so the
 * search UI can flag "you already own this" — Part-DB has the same concept as
 * `ExistingPartFinder` in their InfoProviderSystem.
 *
 * Match key is the canonical (Manufacturer.id, normalized partNumber) pair,
 * piggybacking on `ManufacturerAlias` so vendor casing variants
 * ("STMICROELECTRONICS" vs "STMicroelectronics") all collapse to the same
 * canonical row before lookup.
 */
final readonly class ExistingPartFinder
{
	public function __construct(
		private EntityManagerInterface    $em,
		private ManufacturerCanonicalizer $canonicalizer
	)
	{
	}

	/**
	 * Annotates each candidate in-place with its matching local Part (or null
	 * when no match). One SQL round-trip total — we collect every (mfrId, mpn)
	 * pair, fetch all matching PartManufacturer rows once, then dispatch back.
	 *
	 * @param AggregatedPartCandidate[] $candidates
	 */
	public function annotate(array $candidates): void
	{
		if ($candidates === []) {
			return;
		}

		$keyForCandidate = []; // candidateIdx => "mfrId|mpnNorm" or null
		$pairs = []; // unique "mfrId|mpnNorm" set, plus list of (mfrId, mpnNorm)

		foreach ($candidates as $i => $c) {
			$mfrName = $c->manufacturerName->chosenValue ?? '';
			$mpn = $c->manufacturerPartNumber->chosenValue ?? '';
			if ($mfrName === '' || $mpn === '') {
				$keyForCandidate[$i] = null;
				continue;
			}
			$canonical = $this->canonicalizer->canonicalize($mfrName);
			if ($canonical === null) {
				$keyForCandidate[$i] = null; // unknown manufacturer — cannot already be in DB
				continue;
			}
			$mpnNorm = ManufacturerCanonicalizer::normalize($mpn);
			$key = $canonical->getId() . '|' . $mpnNorm;
			$keyForCandidate[$i] = $key;
			$pairs[$key] = [$canonical->getId(), $mpnNorm];
		}

		if ($pairs === []) {
			return;
		}

		// Pull every PartManufacturer where (manufacturer_id, lower(partNumber))
		// matches any candidate pair. The IN-clause on the composite key is
		// emulated via OR groups; for our typical batch (≤100 pairs) this
		// stays comfortably under MySQL's default placeholder limit.
		$qb = $this->em->createQueryBuilder()
			->select('pm', 'p', 'mfr', 'storage')
			->from(PartManufacturer::class, 'pm')
			->leftJoin('pm.part', 'p')
			->leftJoin('pm.manufacturer', 'mfr')
			->leftJoin('p.storageLocation', 'storage');

		$where = $qb->expr()->orX();
		$paramIdx = 0;
		foreach ($pairs as [$mfrId, $mpnNorm]) {
			$where->add($qb->expr()->andX(
				$qb->expr()->eq('mfr.id', ':mfr' . $paramIdx),
				$qb->expr()->eq('LOWER(pm.partNumber)', ':mpn' . $paramIdx)
			));
			$qb->setParameter('mfr' . $paramIdx, $mfrId);
			$qb->setParameter('mpn' . $paramIdx, $mpnNorm);
			$paramIdx++;
		}
		$qb->where($where);

		/** @var PartManufacturer[] $rows */
		$rows = $qb->getQuery()->getResult();

		$partInfoByKey = [];
		foreach ($rows as $pm) {
			$mfr = $pm->getManufacturer();
			$part = $pm->getPart();
			if ($mfr === null || $part === null || $pm->getPartNumber() === null) {
				continue;
			}
			$key = $mfr->getId() . '|' . ManufacturerCanonicalizer::normalize($pm->getPartNumber());
			if (isset($partInfoByKey[$key])) {
				continue; // first match wins; multi-mfr Part is edge-case
			}
			$partInfoByKey[$key] = $this->buildInfo($part);
		}

		foreach ($keyForCandidate as $i => $key) {
			if ($key !== null && isset($partInfoByKey[$key])) {
				$candidates[$i]->existingPart = $partInfoByKey[$key];
			}
		}
	}

	private function buildInfo(Part $part): ExistingPartInfo
	{
		$storage = $part->getStorageLocation();
		return new ExistingPartInfo(
			partId: $part->getId(),
			partName: $part->getName(),
			partDescription: $part->getDescription(),
			storageLocationName: $storage?->getName(),
			totalStock: $this->totalStock($part)
		);
	}

	private function totalStock(Part $part): int
	{
		$total = 0;
		foreach ($part->getStockLevels() as $level) {
			$total += $level->getStockLevel();
		}
		return $total;
	}
}
