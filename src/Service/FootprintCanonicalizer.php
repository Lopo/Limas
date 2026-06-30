<?php

namespace Limas\Service;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Entity\Footprint;
use Limas\Entity\FootprintAlias;


/**
 * Maps free-form package/footprint strings returned by info providers to the
 * canonical Limas Footprint entity. Lookups go via FootprintAlias
 * (aliasNormalized has UNIQUE index, so they are O(1)).
 *
 * Four-step resolve:
 *  1. FootprintAlias by aliasNormalized — if matched and `footprint` is set,
 *     return it; the alias is also bumped (usageCount++)
 *  2. Fallback: direct case+separator-insensitive name match on Footprint.name
 *     — if matched, also auto-register an alias for next time (verified=auto)
 *  3. Auto-create an unverified alias with footprint=NULL so the admin grid
 *     surfaces it (sorted by usageCount) for human triage
 *  4. Return null — caller leaves Part.footprint blank until admin verifies
 *
 * Normalisation strips dash/space/underscore separators in addition to the
 * lowercase + whitespace collapse from ManufacturerCanonicalizer.
 */
readonly class FootprintCanonicalizer
{
	public function __construct(
		private EntityManagerInterface $em
	)
	{
	}

	public function canonicalize(string $rawName): ?Footprint
	{
		$key = self::normalize($rawName);
		if ($key === '') {
			return null;
		}

		// 1) alias lookup
		$alias = $this->em->getRepository(FootprintAlias::class)
			->findOneBy(['aliasNormalized' => $key]);
		if ($alias !== null) {
			$alias->incrementUsageCount();
			$this->em->flush();
			// Alias hit but footprint not assigned yet → admin must verify;
			// canonicalize stays a miss from the caller's perspective
			return $alias->getFootprint();
		}

		// 2) direct name match → register a verified alias for next time
		foreach ($this->em->getRepository(Footprint::class)->findAll() as $f) {
			if (self::normalize((string)$f->getName()) === $key) {
				$this->registerAliasInternal($rawName, $key, $f, FootprintAlias::SOURCE_AUTO, verified: true);
				return $f;
			}
		}

		// 3) auto-create unverified, footprint=NULL — surfaces in admin grid
		$this->registerAliasInternal($rawName, $key, null, FootprintAlias::SOURCE_AUTO, verified: false);
		return null;
	}

	/**
	 * Register `$rawAlias` as another spelling of `$footprint` from external
	 * code (admin UI or import tools). Idempotent; throws on conflict with
	 * a different footprint.
	 */
	public function registerAlias(Footprint $footprint, string $rawAlias): FootprintAlias
	{
		$normalized = self::normalize($rawAlias);
		if ($normalized === '') {
			throw new \InvalidArgumentException('Cannot register an empty alias.');
		}

		$existing = $this->em->getRepository(FootprintAlias::class)
			->findOneBy(['aliasNormalized' => $normalized]);

		if ($existing !== null) {
			if ($existing->getFootprint() === null) {
				// Pending alias — assign the footprint, mark verified
				$existing->setFootprint($footprint);
				$existing->setVerified(true);
				$this->em->flush();
				return $existing;
			}
			if ($existing->getFootprint()->getId() !== $footprint->getId()) {
				throw new \RuntimeException(sprintf(
					'Alias "%s" already maps to a different footprint (#%d "%s") — cannot reassign to #%d "%s"',
					$normalized,
					$existing->getFootprint()->getId() ?? 0,
					$existing->getFootprint()->getName() ?? '',
					$footprint->getId() ?? 0,
					$footprint->getName() ?? ''
				));
			}
			return $existing;
		}

		return $this->registerAliasInternal($rawAlias, $normalized, $footprint, FootprintAlias::SOURCE_USER, verified: true);
	}

	private function registerAliasInternal(string $alias, string $normalized, ?Footprint $footprint, string $source, bool $verified): FootprintAlias
	{
		$entity = new FootprintAlias($alias, $normalized, $footprint);
		$entity->setSource($source);
		$entity->setVerified($verified);
		$entity->incrementUsageCount();
		try {
			$this->em->persist($entity);
			$this->em->flush();
		} catch (\Throwable) {
			// Race: another request inserted the same normalized form.
			// Re-fetch and bump usage instead.
			$existing = $this->em->getRepository(FootprintAlias::class)
				->findOneBy(['aliasNormalized' => $normalized]);
			if ($existing !== null) {
				$existing->incrementUsageCount();
				$this->em->flush();
				return $existing;
			}
			throw new \RuntimeException('FootprintAlias insert race could not be resolved');
		}
		return $entity;
	}

	/**
	 * Deterministic normalisation for footprint strings:
	 *   - trim
	 *   - collapse runs of whitespace
	 *   - strip dash/space/underscore (separator noise)
	 *   - lowercase
	 */
	public static function normalize(string $name): string
	{
		$collapsed = preg_replace('/\s+/u', ' ', trim($name));
		$stripped = preg_replace('/[-_\s]+/u', '', $collapsed ?? $name);
		return mb_strtolower($stripped ?? $name);
	}
}
