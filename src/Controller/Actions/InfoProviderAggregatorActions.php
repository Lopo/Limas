<?php

namespace Limas\Controller\Actions;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Entity\PartCategory;
use Limas\Entity\StorageLocation;
use Limas\Service\Integration\InfoProvider\AggregatorImporter;
use Limas\Service\Integration\InfoProvider\Dto\AggregatedPartCandidate;
use Limas\Service\Integration\InfoProvider\InfoProviderAggregator;
use Limas\Service\ManufacturerCanonicalizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;


/**
 * REST endpoints over InfoProviderAggregator. Auth piggy-backs on the existing
 * `^/api/.*` firewall in config/packages/security.yaml
 *
 *   GET  /api/distributor-aggregator/sources
 *     returns the list of currently configured adapters + their capabilities
 *
 *   GET  /api/distributor-aggregator/search?mpn=1N4148
 *     [&merged=1]                   default raw per-source; merged=1 → AggregatedPartCandidate[]
 *     [&sources=tme,digikey]        restrict to listed adapters
 *     [&limit=3]                    max results per provider (raw) or candidates (merged)
 *     [&priority=digikey,tme,…]     override trust order for the merge
 *     [&mergeStrategy=hierarchy]    'majority' (default) or 'hierarchy'
 *
 *   POST /api/distributor-aggregator/import
 *     body: { mpn, manufacturer, categoryId, storageLocationId, [sources] }
 *     re-runs aggregator+merge, picks the candidate matching (manufacturer, MPN),
 *     and creates a fully linked Part. Returns {partId, ...summary}.
 */
class InfoProviderAggregatorActions
	extends AbstractController
{
	public function __construct(
		private readonly InfoProviderAggregator $aggregator,
		private readonly AggregatorImporter     $importer,
		private readonly EntityManagerInterface $em,
		private readonly SerializerInterface    $serializer
	)
	{
	}

	#[Route(path: '/api/distributor-aggregator/sources', methods: ['GET'])]
	public function sourcesAction(): JsonResponse
	{
		return new JsonResponse([
			'sources' => $this->aggregator->sourcesWithCapabilities(),
			'defaults' => [
				'priority' => $this->aggregator->defaultPriority(),
				'mergeStrategy' => 'majority'
			]
		]);
	}

	#[Route(path: '/api/distributor-aggregator/search', methods: ['GET'])]
	public function searchAction(Request $request): JsonResponse
	{
		// 2-phase merged fan-out across 3+ providers easily exceeds the default
		// PHP max_execution_time (30s) for popular MPNs (ULN2003, 1N4148, …) —
		// disable both knobs for this endpoint. Sysadmin-level PHP-FPM
		// request_terminate_timeout still applies. Long-term fix: parallelise
		// HTTP via Symfony HttpClient (Guzzle sync is the bottleneck).
		set_time_limit(0);
		ini_set('max_execution_time', '0');

		$mpn = trim($request->query->get('mpn', ''));
		if ($mpn === '') {
			return new JsonResponse(['error' => 'mpn query parameter is required'], 400);
		}

		// 20 covers ~99 % of electronics MPN searches in one fan-out (typical
		// part has 2-7 manufacturer variants). Up to 100 for ambiguous
		// designations (FG-06 et al — same string, dozens of unrelated
		// manufacturers). TME chunks internally >50; cap at 100 because
		// beyond that grid UX degrades faster than completeness improves.
		$limit = max(1, min(100, (int)$request->query->get('limit', 20)));
		$merged = $request->query->getBoolean('merged');
		// `phase=light` skips Phase 2 entirely — returns merged candidates
		// without parameter / pricing / completion. FE uses this for the
		// initial candidate grid and then POSTs /deepen for the row the
		// user selects.
		$phase = $request->query->get('phase', 'full');
		$nocache = $request->query->getBoolean('nocache');
		$sourcesParam = $request->query->get('sources', '');
		$sources = $sourcesParam !== '' ? array_map('trim', explode(',', $sourcesParam)) : null;
		// `completeAll=1` lifts the per-query completion-pass cap so EVERY
		// incomplete candidate gets its missing sources filled in. Off by
		// default to keep wallclock + HTTP fan-out bounded; UI exposes it
		// via the "Complete more" button shown when the default cap clipped
		// some candidates.
		$completeAll = $request->query->getBoolean('completeAll');
		$completionCap = $completeAll ? PHP_INT_MAX : InfoProviderAggregator::COMPLETION_AUTO_CAP;
		$priorityParam = $request->query->get('priority', '');
		$priority = $priorityParam !== '' ? array_values(array_filter(array_map('trim', explode(',', $priorityParam)), static fn(string $s): bool => $s !== '')) : null;
		$strategyParam = $request->query->get('mergeStrategy', '');
		$strategy = $strategyParam !== '' ? $strategyParam : null;

		$agg = $nocache ? $this->aggregator->withBypassCache() : $this->aggregator;
		$agg = $agg->withMergeOverride($priority, $strategy);
		if ($merged && $phase === 'light') {
			$payload = $agg->searchByMpnAndMergeLight($mpn, $sources, $limit);
		} elseif ($merged) {
			$payload = $agg->searchByMpnAndMerge($mpn, $sources, $limit, $completionCap);
		} else {
			$payload = $agg->searchByMpn($mpn, $sources, $limit);
		}

		// Serializer handles readonly DTO properties via property access automatically;
		// IGNORED_ATTRIBUTES strips `rawSource` to keep payloads small.
		$json = $this->serializer->serialize($payload, 'json', [
			AbstractObjectNormalizer::SKIP_NULL_VALUES => false,
			AbstractObjectNormalizer::IGNORED_ATTRIBUTES => ['rawSource']
		]);

		return new JsonResponse($json, 200, [], true);
	}

	/**
	 * Resolve a distributor product-detail URL into `{mpn, manufacturer}` —
	 * the FE uses this to preflight a paste-URL form before letting the
	 * user pick a category/storage. Returns 404 when no URLHandler
	 * adapter recognises the URL, so the FE can surface a clear error.
	 *
	 * GET /api/distributor-aggregator/resolve-url?url=https://...
	 */
	#[Route(path: '/api/distributor-aggregator/resolve-url', methods: ['GET'])]
	public function resolveUrlAction(Request $request): JsonResponse
	{
		$url = trim($request->query->get('url', ''));
		if ($url === '') {
			return new JsonResponse(['error' => 'url query parameter is required'], 400);
		}
		$resolved = $this->aggregator->resolveUrl($url);
		if ($resolved === null) {
			return new JsonResponse(['error' => 'No InfoProvider recognises this URL', 'url' => $url], 404);
		}
		return new JsonResponse($resolved);
	}

	/**
	 * Deepen a single light candidate. Body: `{sources: {<sourceName>: <sku>, ...}}`
	 * — exactly the map the FE reads from the light candidate's
	 * `providerSpecific[<source>].sourceSku`. Returns the heavy
	 * `AggregatedPartCandidate` (with parameters + pricing + lifecycle
	 * + existingPart annotation) or 404 if every Phase-2 fetch failed.
	 *
	 * POST /api/distributor-aggregator/deepen
	 */
	#[Route(path: '/api/distributor-aggregator/deepen', methods: ['POST'])]
	public function deepenAction(Request $request): JsonResponse
	{
		set_time_limit(0);
		ini_set('max_execution_time', '0');

		$body = json_decode($request->getContent(), true);
		if (!is_array($body)) {
			return new JsonResponse(['error' => 'Body must be a JSON object'], 400);
		}
		$sources = $body['sources'] ?? null;
		if (!is_array($sources) || $sources === []) {
			return new JsonResponse(['error' => '`sources` map of {source: sku} is required'], 400);
		}
		$sourceSkuMap = [];
		foreach ($sources as $name => $sku) {
			if (is_string($name) && is_string($sku) && $sku !== '') {
				$sourceSkuMap[$name] = $sku;
			}
		}
		if ($sourceSkuMap === []) {
			return new JsonResponse(['error' => 'No usable (source, sku) pairs in body'], 400);
		}

		$nocache = (bool)($body['nocache'] ?? false);
		$priority = isset($body['priority']) && is_array($body['priority']) ? $body['priority'] : null;
		$strategy = is_string($body['mergeStrategy'] ?? null) ? $body['mergeStrategy'] : null;

		$agg = $nocache ? $this->aggregator->withBypassCache() : $this->aggregator;
		$agg = $agg->withMergeOverride($priority, $strategy);
		$candidate = $agg->deepenCandidate($sourceSkuMap);
		if ($candidate === null) {
			return new JsonResponse(['error' => 'Every source failed to return Phase-2 detail'], 404);
		}

		$json = $this->serializer->serialize($candidate, 'json', [
			AbstractObjectNormalizer::SKIP_NULL_VALUES => false,
			AbstractObjectNormalizer::IGNORED_ATTRIBUTES => ['rawSource']
		]);
		return new JsonResponse($json, 200, [], true);
	}

	#[Route(path: '/api/distributor-aggregator/import', methods: ['POST'])]
	public function importAction(Request $request): JsonResponse
	{
		// Same reasoning as searchAction — import re-runs the merge internally.
		set_time_limit(0);
		ini_set('max_execution_time', '0');

		$body = json_decode($request->getContent(), true);
		if (!is_array($body)) {
			return new JsonResponse(['error' => 'Body must be a JSON object'], 400);
		}

		$mpn = trim((string)($body['mpn'] ?? ''));
		$mfr = trim((string)($body['manufacturer'] ?? ''));
		$categoryId = (int)($body['categoryId'] ?? 0);
		$storageId = (int)($body['storageLocationId'] ?? 0);
		$sources = is_array($body['sources'] ?? null) ? array_map('strval', $body['sources']) : null;
		$limit = max(1, min(50, (int)($body['limit'] ?? 10)));

		$missing = [];
		if ($mpn === '') $missing[] = 'mpn';
		if ($mfr === '') $missing[] = 'manufacturer';
		if ($categoryId <= 0) $missing[] = 'categoryId';
		if ($storageId <= 0) $missing[] = 'storageLocationId';
		if ($missing !== []) {
			return new JsonResponse(['error' => 'Missing/invalid: ' . implode(', ', $missing)], 400);
		}

		$category = $this->em->find(PartCategory::class, $categoryId);
		$storage = $this->em->find(StorageLocation::class, $storageId);
		if ($category === null) {
			return new JsonResponse(['error' => "PartCategory #$categoryId not found"], 404);
		}
		if ($storage === null) {
			return new JsonResponse(['error' => "StorageLocation #$storageId not found"], 404);
		}

		$candidates = $this->aggregator->searchByMpnAndMerge($mpn, $sources, $limit);
		$picked = $this->findCandidate($candidates, $mfr, $mpn);
		if ($picked === null) {
			return new JsonResponse([
				'error' => sprintf('No matching candidate for manufacturer "%s" + MPN "%s"', $mfr, $mpn),
				'available' => array_map(static fn(AggregatedPartCandidate $c) => [
					'manufacturer' => $c->manufacturerName->chosenValue,
					'mpn' => $c->manufacturerPartNumber->chosenValue,
					'sources' => $c->contributingSources
				], $candidates)
			], 404);
		}

		$part = $this->importer->import($picked, $category, $storage);
		return new JsonResponse([
			'partId' => $part->getId(),
			'partName' => $part->getName(),
			'manufacturers' => count($part->getManufacturers()),
			'distributors' => count($part->getDistributors()),
			'parameters' => count($part->getParameters()),
			'contributingSources' => $picked->contributingSources,
			'conflicts' => $picked->conflicts
		], 201);
	}

	/**
	 * @param AggregatedPartCandidate[] $candidates
	 */
	private function findCandidate(array $candidates, string $manufacturer, string $mpn): ?AggregatedPartCandidate
	{
		$mpnKey = ManufacturerCanonicalizer::normalize($mpn);
		$mfrKey = ManufacturerCanonicalizer::normalize($manufacturer);
		foreach ($candidates as $c) {
			$cMpn = ManufacturerCanonicalizer::normalize($c->manufacturerPartNumber->chosenValue ?? '');
			$cMfr = ManufacturerCanonicalizer::normalize($c->manufacturerName->chosenValue ?? '');
			if ($cMpn === $mpnKey && $cMfr === $mfrKey) {
				return $c;
			}
		}
		return null;
	}
}
