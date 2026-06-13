<?php

namespace Limas\Service;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Entity\Manufacturer;
use Limas\Entity\ManufacturerAlias;


/**
 * Maps free-form manufacturer name strings returned by info providers to the
 * canonical Limas Manufacturer entity. Lookups go via ManufacturerAlias
 * (aliasNormalized has UNIQUE index, so they are O(1)).
 *
 * `canonicalize()` is read-only; `registerAlias()` (called by the importer)
 * is the only place new aliases enter the table.
 */
readonly class ManufacturerCanonicalizer
{
	public function __construct(
		private EntityManagerInterface $em
	)
	{
	}

	/**
	 * Find the canonical Manufacturer for a free-form name, or null if neither
	 * a registered alias nor a direct Manufacturer-name lookup matches
	 *
	 * Two-step:
	 *  1. ManufacturerAlias table (canonical aliases like "ONSEMI" → ON Semi)
	 *  2. Fallback: case-insensitive direct match on Manufacturer.name.
	 *
	 * Step 2 is important because manufacturers freshly created on-the-fly
	 * (aggregator's auto-create Octopart-style flow) don't get an alias row
	 * yet — without the fallback, ExistingPartFinder would miss them.
	 */
	public function canonicalize(string $rawName): ?Manufacturer
	{
		$key = self::normalize($rawName);
		if ($key === '') {
			return null;
		}
		$alias = $this->em->getRepository(ManufacturerAlias::class)
			->findOneBy(['aliasNormalized' => $key]);
		if ($alias !== null) {
			return $alias->getManufacturer();
		}
		$direct = $this->em->createQueryBuilder()
			->select('m')
			->from(Manufacturer::class, 'm')
			->where('LOWER(m.name) = :key')
			->setParameter('key', $key)
			->setMaxResults(1)
			->getQuery()
			->getOneOrNullResult();
		return $direct instanceof Manufacturer ? $direct : null;
	}

	/**
	 * Register `$rawAlias` (preserving original casing in `alias`) as another
	 * spelling of `$manufacturer`. Idempotent — if the normalized form already
	 * exists pointing at the same manufacturer, returns the existing entity.
	 *
	 * Throws if the same normalized form already points to a DIFFERENT
	 * manufacturer — that would silently merge unrelated companies otherwise.
	 */
	public function registerAlias(Manufacturer $manufacturer, string $rawAlias): ManufacturerAlias
	{
		$normalized = self::normalize($rawAlias);
		if ($normalized === '') {
			throw new \InvalidArgumentException('Cannot register an empty alias.');
		}

		$existing = $this->em->getRepository(ManufacturerAlias::class)
			->findOneBy(['aliasNormalized' => $normalized]);

		if ($existing !== null) {
			if ($existing->getManufacturer()->getId() !== $manufacturer->getId()) {
				throw new \RuntimeException(sprintf(
					'Alias "%s" already maps to a different manufacturer (#%d "%s") — cannot reassign to #%d "%s"',
					$normalized,
					$existing->getManufacturer()->getId() ?? 0,
					$existing->getManufacturer()->getName(),
					$manufacturer->getId() ?? 0,
					$manufacturer->getName()
				));
			}
			return $existing;
		}

		$alias = new ManufacturerAlias($manufacturer, $rawAlias, $normalized);
		$this->em->persist($alias);
		$this->em->flush();
		return $alias;
	}

	/**
	 * Deterministic normalisation:
	 *   - trim
	 *   - collapse runs of whitespace to single space
	 *   - lowercase
	 * Static so the InfoProviderMerger can use it as a fallback grouping key
	 * without depending on the entity manager.
	 */
	public static function normalize(string $name): string
	{
		$collapsed = preg_replace('/\s+/u', ' ', trim($name));
		return mb_strtolower($collapsed ?? $name);
	}
}
