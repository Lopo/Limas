<?php declare(strict_types=1);

namespace Limas\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Limas\Entity\ParameterAlias;


/**
 * Seed + maintenance fixture for ParameterAlias.
 *
 * Reads two JSON files under data/parameter-taxonomy/:
 *  - octopart-attributes.json — 757-entry attribute taxonomy scraped from
 *    octopart.com/api/v4/values; vendor=NULL global aliases, marked as
 *    source='octopart' verified=true.
 *  - vendor-aliases.json — per-DigiKey/Farnell/TME rawName→canonical
 *    overrides that bridge "Package / Case" → "Case/Package", etc.;
 *    source='vendor' verified=true.
 *
 * Idempotent: existing (rawNameNormalized, vendor) tuples get UPDATEd
 * (canonical, source, verified, shortname). Pre-existing `source='auto'`
 * rows discovered through normal aggregator traffic get promoted to
 * 'vendor' when a manual mapping arrives — usageCount preserved.
 *
 * Run with:
 *   php bin/console doctrine:fixtures:load --group=parameter-taxonomy --append
 *
 * Use `--append` so the fixture loader doesn't purge the entire DB; this
 * fixture only touches the ParameterAlias table.
 */
class ParameterAliasFixtures
	extends Fixture
	implements FixtureGroupInterface
{
	public static function getGroups(): array
	{
		return ['parameter-taxonomy'];
	}

	public function load(ObjectManager $manager): void
	{
		$baseDir = dirname(__DIR__, 2) . '/data/parameter-taxonomy';

		$octopartCount = $this->seedOctopart($manager, $baseDir . '/octopart-attributes.json');
		$vendorCount = $this->seedVendor($manager, $baseDir . '/vendor-aliases.json');

		$manager->flush();

		// Backfill shortname on every row where it's NULL but the
		// canonicalName matches an octopart-source row's canonical. Lets
		// vendor mappings (DigiKey "Package / Case" → "Case/Package") and
		// auto-discovered rows that happened to land on an octopart canonical
		// inherit the stable shortname for free.
		$linked = $this->backfillShortnames($manager);
		$manager->flush();

		fwrite(STDERR, sprintf(
			"ParameterAliasFixtures: %d octopart upserts, %d vendor upserts, %d shortname links.\n",
			$octopartCount, $vendorCount, $linked
		));
	}

	private function backfillShortnames(ObjectManager $manager): int
	{
		$repo = $manager->getRepository(ParameterAlias::class);
		/** @var ParameterAlias[] $canonicals */
		$canonicals = $repo->createQueryBuilder('a')
			->where('a.source = :src')
			->andWhere('a.shortname IS NOT NULL')
			->setParameter('src', ParameterAlias::SOURCE_OCTOPART)
			->getQuery()
			->getResult();
		$shortnameByCanonical = [];
		foreach ($canonicals as $row) {
			$shortnameByCanonical[$row->getCanonicalName()] = $row->getShortname();
		}

		/** @var ParameterAlias[] $needLinking */
		$needLinking = $repo->createQueryBuilder('a')
			->where('a.shortname IS NULL')
			->andWhere('a.canonicalName IN (:canonicals)')
			->setParameter('canonicals', array_keys($shortnameByCanonical))
			->getQuery()
			->getResult();

		$count = 0;
		foreach ($needLinking as $row) {
			$short = $shortnameByCanonical[$row->getCanonicalName()] ?? null;
			if ($short !== null) {
				$row->setShortname($short);
				$count++;
			}
		}
		return $count;
	}

	private function seedOctopart(ObjectManager $manager, string $path): int
	{
		if (!is_file($path)) {
			return 0;
		}
		$entries = json_decode((string)file_get_contents($path), true);
		if (!is_array($entries)) {
			return 0;
		}
		$count = 0;
		$repo = $manager->getRepository(ParameterAlias::class);
		foreach ($entries as $e) {
			$name = (string)($e['name'] ?? '');
			$shortname = (string)($e['shortname'] ?? '');
			if ($name === '' || $shortname === '') {
				continue;
			}
			$normalized = ParameterAlias::normalize($name);
			$existing = $repo->findOneBy(['rawNameNormalized' => $normalized, 'vendor' => null]);
			if ($existing === null) {
				$alias = new ParameterAlias($name, $name, null);
				$alias->setSource(ParameterAlias::SOURCE_OCTOPART);
				$alias->setShortname($shortname);
				$alias->setVerified(true);
				$manager->persist($alias);
			} else {
				// Don't overwrite a user-promoted alias with the octopart base
				// — but DO refresh shortname + verified state when missing.
				if ($existing->getShortname() === null) {
					$existing->setShortname($shortname);
				}
				if (!$existing->isVerified() && $existing->getSource() === ParameterAlias::SOURCE_AUTO) {
					$existing->setSource(ParameterAlias::SOURCE_OCTOPART);
					$existing->setVerified(true);
					$existing->setCanonicalName($name);
				}
			}
			$count++;
		}
		return $count;
	}

	private function seedVendor(ObjectManager $manager, string $path): int
	{
		if (!is_file($path)) {
			return 0;
		}
		$payload = json_decode((string)file_get_contents($path), true);
		if (!is_array($payload)) {
			return 0;
		}
		$count = 0;
		$repo = $manager->getRepository(ParameterAlias::class);
		foreach ($payload as $vendor => $map) {
			if ($vendor === '_comment' || !is_string($vendor) || !is_array($map)) {
				continue;
			}
			foreach ($map as $rawName => $canonical) {
				if (!is_string($rawName) || !is_string($canonical) || $rawName === '' || $canonical === '') {
					continue;
				}
				$normalized = ParameterAlias::normalize($rawName);
				$existing = $repo->findOneBy(['rawNameNormalized' => $normalized, 'vendor' => $vendor]);
				if ($existing === null) {
					$alias = new ParameterAlias($rawName, $canonical, $vendor);
					$alias->setSource(ParameterAlias::SOURCE_VENDOR);
					$alias->setVerified(true);
					$manager->persist($alias);
				} else {
					// Promote auto-discovered → vendor mapping; preserve usage.
					$existing->setCanonicalName($canonical);
					$existing->setSource(ParameterAlias::SOURCE_VENDOR);
					$existing->setVerified(true);
				}
				$count++;
			}
		}
		return $count;
	}
}
