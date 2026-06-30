<?php

namespace Limas\Service;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Entity\Manufacturer;
use Limas\Entity\ManufacturerAlias;
use Limas\Entity\PartManufacturer;


/**
 * Maps free-form manufacturer name strings returned by info providers to the
 * canonical Limas Manufacturer entity. Lookups go via ManufacturerAlias
 * (aliasNormalized has UNIQUE index, so they are O(1)).
 *
 * Four-step resolve (mirrors FootprintCanonicalizer):
 *  1. ManufacturerAlias by aliasNormalized — if matched and `manufacturer` is
 *     set, bump usageCount and return it; if alias exists but manufacturer is
 *     null (pending admin verification), still bump usage but return null
 *  2. Fallback: direct case-insensitive name match on Manufacturer.name — if
 *     matched, auto-register a verified alias for next time
 *  3. Auto-create an unverified alias with manufacturer=NULL so the admin grid
 *     surfaces it (sorted by usageCount) for human triage
 *  4. Return null — caller (importer) creates a new Manufacturer and assigns
 *     it to the pending alias
 */
readonly class ManufacturerCanonicalizer
{
	public function __construct(
		private EntityManagerInterface $em
	)
	{
	}

	public function canonicalize(string $rawName): ?Manufacturer
	{
		$key = self::normalize($rawName);
		if ($key === '') {
			return null;
		}

		// 1) alias lookup
		$alias = $this->em->getRepository(ManufacturerAlias::class)
			->findOneBy(['aliasNormalized' => $key]);
		if ($alias !== null) {
			$alias->incrementUsageCount();
			$this->em->flush();
			// Alias hit but manufacturer not assigned yet → admin must verify;
			// canonicalize stays a miss from the caller's perspective
			return $alias->getManufacturer();
		}

		// 2) direct name match → register a verified alias for next time
		$direct = $this->em->createQueryBuilder()
			->select('m')
			->from(Manufacturer::class, 'm')
			->where('LOWER(m.name) = :key')
			->setParameter('key', $key)
			->setMaxResults(1)
			->getQuery()
			->getOneOrNullResult();
		if ($direct instanceof Manufacturer) {
			$this->registerAliasInternal($rawName, $key, $direct, ManufacturerAlias::SOURCE_AUTO, verified: true);
			return $direct;
		}

		// 3) auto-create unverified, manufacturer=NULL — surfaces in admin grid
		$this->registerAliasInternal($rawName, $key, null, ManufacturerAlias::SOURCE_AUTO, verified: false);
		return null;
	}

	/**
	 * Register `$rawAlias` as another spelling of `$manufacturer` from external
	 * code (admin UI or import tools). Idempotent; throws on conflict with a
	 * different manufacturer
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
			if ($existing->getManufacturer() === null) {
				// Pending alias — assign the manufacturer, mark verified
				$existing->setManufacturer($manufacturer);
				$existing->setVerified(true);
				$this->em->flush();
				return $existing;
			}
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

		return $this->registerAliasInternal($rawAlias, $normalized, $manufacturer, ManufacturerAlias::SOURCE_USER, verified: true);
	}

	/**
	 * Merge `$source` into `$target`:
	 *   - reassign every PartManufacturer.manufacturer FK from source → target
	 *   - reassign every existing ManufacturerAlias pointing at source → target
	 *     (with conflict resolution if the same normalized alias already maps
	 *     to target — bump usage, drop the dupe)
	 *   - record source's own name as a verified alias of target (so future
	 *     imports of source's spelling resolve to target automatically)
	 *   - delete source
	 *
	 * Returns the number of PartManufacturer rows reassigned, for the UX
	 * confirmation toast.
	 */
	public function mergeInto(Manufacturer $source, Manufacturer $target): int
	{
		if ($source->getId() === $target->getId()) {
			throw new \InvalidArgumentException('Cannot merge a manufacturer into itself.');
		}

		$conn = $this->em->getConnection();
		$conn->beginTransaction();
		try {
			$reassigned = (int)$this->em->createQueryBuilder()
				->update(PartManufacturer::class, 'pm')
				->set('pm.manufacturer', ':target')
				->where('pm.manufacturer = :source')
				->setParameter('target', $target)
				->setParameter('source', $source)
				->getQuery()
				->execute();

			// Migrate existing aliases pointing at source → target
			$sourceAliases = $this->em->getRepository(ManufacturerAlias::class)
				->findBy(['manufacturer' => $source]);
			foreach ($sourceAliases as $alias) {
				$existing = $this->em->getRepository(ManufacturerAlias::class)
					->findOneBy(['aliasNormalized' => $alias->getAliasNormalized()]);
				if ($existing !== null && $existing !== $alias && $existing->getManufacturer()?->getId() === $target->getId()) {
					// Same normalized key already maps to target — keep that one,
					// drop the dupe coming from source
					$existing->incrementUsageCount();
					$this->em->remove($alias);
					continue;
				}
				$alias->setManufacturer($target);
				$alias->setVerified(true);
			}

			// Cache source's own name as an alias of target — future imports
			// of "Bivar Inc." spelling resolve directly to "Bivar"
			$sourceName = (string)$source->getName();
			$sourceKey = self::normalize($sourceName);
			if ($sourceKey !== '') {
				$existing = $this->em->getRepository(ManufacturerAlias::class)
					->findOneBy(['aliasNormalized' => $sourceKey]);
				if ($existing === null) {
					$this->registerAliasInternal($sourceName, $sourceKey, $target, ManufacturerAlias::SOURCE_USER, verified: true);
				} elseif ($existing->getManufacturer() === null) {
					$existing->setManufacturer($target);
					$existing->setVerified(true);
				} elseif ($existing->getManufacturer()->getId() !== $target->getId()) {
					$existing->setManufacturer($target);
					$existing->setVerified(true);
				}
			}

			$this->em->remove($source);
			$this->em->flush();
			$conn->commit();
			return $reassigned;
		} catch (\Throwable $e) {
			$conn->rollBack();
			throw $e;
		}
	}

	private function registerAliasInternal(string $alias, string $normalized, ?Manufacturer $manufacturer, string $source, bool $verified): ManufacturerAlias
	{
		$entity = new ManufacturerAlias($alias, $normalized, $manufacturer);
		$entity->setSource($source);
		$entity->setVerified($verified);
		$entity->incrementUsageCount();
		try {
			$this->em->persist($entity);
			$this->em->flush();
		} catch (\Throwable) {
			// Race: another request inserted the same normalized form.
			// Re-fetch and bump usage instead.
			$existing = $this->em->getRepository(ManufacturerAlias::class)
				->findOneBy(['aliasNormalized' => $normalized]);
			if ($existing !== null) {
				$existing->incrementUsageCount();
				$this->em->flush();
				return $existing;
			}
			throw new \RuntimeException('ManufacturerAlias insert race could not be resolved');
		}
		return $entity;
	}

	/**
	 * Deterministic normalisation:
	 *   - trim
	 *   - collapse runs of whitespace to single space
	 *   - lowercase
	 * Static so the InfoProviderMerger can use it as a fallback grouping key
	 * without depending on the entity manager
	 */
	public static function normalize(string $name): string
	{
		$collapsed = preg_replace('/\s+/u', ' ', trim($name));
		return mb_strtolower($collapsed ?? $name);
	}
}
