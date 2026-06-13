<?php

namespace Limas\Service\Integration\InfoProvider;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Entity\ParameterAlias;


/**
 * Resolves a vendor's `rawName` for a parameter to a canonical display name
 * by walking the `ParameterAlias` table
 *
 * Lookup order:
 *   1. Exact (rawNameNormalized, vendor) — per-vendor alias takes precedence.
 *   2. Exact (rawNameNormalized, vendor=NULL) — global alias.
 *   3. Suffix-stripped fallback — try the same lookups again after dropping
 *      common noise suffixes (" value", " rating", " (typ)", " (max)", …).
 *      Catches "Resistance Value" → maps to "Resistance" without manual alias.
 *   4. Auto-create — first time we see this rawName, insert a row with
 *      canonical=raw, source='auto', verified=false. Admin UI can later
 *      merge it into an existing canonical.
 *
 * Each successful resolution bumps the alias's `usageCount` so the admin UI
 * can sort "most-used unverified" to the top.
 */
final class ParameterNormalizer
{
	// Only PURE noise suffixes here — qualifiers like (max)/(min)/(typ) carry
	// real semantic value (min/typical/max electronics triple) that PartParameter
	// stores as separate columns. Stripping them would silently collapse two
	// independent measurements onto a single row with first-wins truncation,
	// so we keep them as distinct canonicals until the value-parser ticket
	// lands and can route them into PartParameter.{minValue,value,maxValue}.
	private const array SUFFIX_NOISE = [
		' value', // Farnell: "Resistance Value" → "Resistance"
		' rating' // Octopart already has "Power Rating" canonical so this
		// only fires when the direct lookup misses; safe to keep
	];


	public function __construct(
		private readonly EntityManagerInterface $em
	)
	{
	}

	/**
	 * @return string canonical display name; ALWAYS a non-empty string
	 */
	public function canonicalize(string $rawName, ?string $vendor = null): string
	{
		$rawName = trim($rawName);
		if ($rawName === '') {
			return '';
		}
		$normalized = ParameterAlias::normalize($rawName);

		// 1) per-vendor → 2) global
		$hit = $this->lookup($normalized, $vendor)
			?? ($vendor !== null ? $this->lookup($normalized, null) : null);

		// 3) suffix-stripped retry
		if ($hit === null) {
			$stripped = $this->stripNoiseSuffix($normalized);
			if ($stripped !== null && $stripped !== $normalized) {
				$hit = $this->lookup($stripped, $vendor)
					?? ($vendor !== null ? $this->lookup($stripped, null) : null);
			}
		}

		if ($hit !== null) {
			$hit->incrementUsageCount();
			$this->em->flush();
			return $hit->getCanonicalName();
		}

		// 4) auto-create unverified
		$alias = new ParameterAlias($rawName, $rawName, $vendor);
		$alias->setSource(ParameterAlias::SOURCE_AUTO);
		$alias->incrementUsageCount();
		try {
			$this->em->persist($alias);
			$this->em->flush();
		} catch (\Throwable) {
			// Race: another request inserted the same (normalized, vendor) tuple
			// between lookup and persist. Detach the now-broken UoW entry and
			// re-fetch (doctrine/orm 3+ dropped the per-class clear($className)
			// overload — full clear() would purge the surrounding request's
			// UoW, way too destructive).
			$this->em->detach($alias);
			$existing = $this->lookup($normalized, $vendor)
				?? ($vendor !== null ? $this->lookup($normalized, null) : null);
			if ($existing !== null) {
				return $existing->getCanonicalName();
			}
		}
		return $rawName;
	}

	private function lookup(string $normalized, ?string $vendor): ?ParameterAlias
	{
		return $this->em->getRepository(ParameterAlias::class)
			->findOneBy(['rawNameNormalized' => $normalized, 'vendor' => $vendor]);
	}

	private function stripNoiseSuffix(string $normalized): ?string
	{
		foreach (self::SUFFIX_NOISE as $suffix) {
			if (str_ends_with($normalized, $suffix)) {
				$candidate = trim(substr($normalized, 0, -strlen($suffix)));
				return $candidate !== '' ? $candidate : null;
			}
		}
		return null;
	}
}
