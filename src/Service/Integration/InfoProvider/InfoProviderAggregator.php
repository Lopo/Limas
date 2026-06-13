<?php

namespace Limas\Service\Integration\InfoProvider;

use Limas\Service\Integration\InfoProvider\Contract\InfoProviderInterface;
use Limas\Service\Integration\InfoProvider\Contract\URLHandlerInterface;
use Limas\Service\Integration\InfoProvider\Dto\AggregatedPartCandidate;
use Limas\Service\Integration\InfoProvider\Dto\InfoProviderResult;
use Limas\Service\Integration\InfoProvider\Dto\InfoProviderSearchResult;
use Limas\Service\Integration\InfoProvider\Merger\HierarchyMergeStrategy;
use Limas\Service\Integration\InfoProvider\Merger\InfoProviderMerger;
use Limas\Service\Integration\InfoProvider\Merger\MajorityMergeStrategy;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\Cache\CacheInterface;


/**
 * Two-phase fan-out across configured InfoProvider adapters
 *
 *  - `searchByMpn()` — Phase 1 only, light SearchResult[] per provider.
 *  - `getDetails()`  — Phase 2 single-provider detail by sourceSku.
 *  - `searchByMpnAndMerge()` — Phase 1 across providers → group by canonical
 *     (manufacturer, MPN) → Phase 2 upgrade per matched provider → merge.
 *
 * Merging across providers happens on FULL details (parameters + price breaks
 * available), so the merger sees consistent data even though search payloads
 * are intentionally light.
 */
final class InfoProviderAggregator
{
	/**
	 * Default cap for the post-merge completion pass: only the top-N
	 * incomplete candidates get their missing sources auto-fetched. Beyond
	 * this, the user has to opt in via the "Complete more" UI gesture, which
	 * raises the cap effectively to unlimited. Picked so default extra HTTP
	 * per query stays bounded: 10 candidates × ≤2 missing sources × ≤3 calls
	 * each = ≤60 extra dispatches, all cached, mostly parallel via curl_multi.
	 */
	public const int COMPLETION_AUTO_CAP = 10;

	private bool $bypassCache = false;


	/**
	 * @param iterable<InfoProviderInterface> $adapters Tagged limas.info_provider
	 * @param iterable<URLHandlerInterface> $urlHandlers Tagged limas.url_handler — distributor URL parsers (may overlap with $adapters when an adapter implements both)
	 */
	public function __construct(
		private readonly iterable             $adapters,
		private InfoProviderMerger            $merger,
		private readonly CacheInterface       $aggregatorCache,
		private readonly ExistingPartFinder   $existingPartFinder,
		private readonly ParameterNormalizer  $parameterNormalizer,
		private readonly ParameterValueParser $parameterValueParser,
		private readonly iterable             $urlHandlers = [],
		private readonly array                $defaultPriority = ['digikey', 'mouser', 'farnell', 'tme', 'arrow']
	)
	{
	}

	/**
	 * Per-request merge override. Returns a clone whose merger uses the
	 * caller-supplied strategy (e.g. user picked Hierarchy with their own
	 * trust order in the settings dialog). Null defaults keep the DI strategy.
	 *
	 * `$strategyKey` accepted values: 'majority', 'hierarchy'. Unknown → DI default.
	 *
	 * @param string[]|null $priority Distributor names from most to least trusted.
	 *                                Null = use default priority. Empty array = use default.
	 */
	public function withMergeOverride(?array $priority = null, ?string $strategyKey = null): self
	{
		if ($strategyKey === null && $priority === null) {
			return $this;
		}
		$effectivePriority = ($priority !== null && $priority !== []) ? $priority : $this->defaultPriority;
		$strategy = match (strtolower($strategyKey ?? '')) {
			'hierarchy' => new HierarchyMergeStrategy($effectivePriority),
			'majority' => new MajorityMergeStrategy($effectivePriority),
			default => $priority !== null ? new MajorityMergeStrategy($effectivePriority) : null
		};
		if ($strategy === null) {
			return $this;
		}
		$clone = clone $this;
		$clone->merger = $this->merger->withStrategy($strategy);
		return $clone;
	}

	/**
	 * Backend default priority (the order in which sources are trusted as
	 * tie-breakers in Majority and as the lookup order in Hierarchy). Surfaced
	 * via `/sources` so the frontend settings dialog can offer "Reset to
	 * defaults" matching what services.yaml configured.
	 *
	 * @return string[]
	 */
	public function defaultPriority(): array
	{
		return $this->defaultPriority;
	}

	/**
	 * One-shot bypass — used by REST callers that pass `nocache=1`. Resets to
	 * false automatically after the next searchByMpn / searchByMpnAndMerge run.
	 */
	public function withBypassCache(): self
	{
		$clone = clone $this;
		$clone->bypassCache = true;
		return $clone;
	}

	/**
	 * Phase 1 light search across every configured adapter — parallel
	 *
	 * Pattern: collect lazy `ResponseInterface`s from every adapter's
	 * `searchByMpnAsync()` BEFORE awaiting any. Symfony HttpClient's curl_multi
	 * pipelines them, so total wallclock ≈ max(per-provider) instead of sum.
	 *
	 * @param array<string>|null $enabledSources null = all configured; otherwise restrict to listed adapter names
	 * @return array<string, InfoProviderSearchResult[]|array{error: string}> keyed by adapter name
	 */
	public function searchByMpn(string $mpn, ?array $enabledSources = null, int $limit = 10): array
	{
		$pending = [];   // adapterName => [adapter, responses[], cacheKey]
		$results = [];

		// Per-provider cache lookup first — sources we already have within
		// TTL never re-hit the network. Cache miss falls through to async
		// dispatch + populate-on-await.
		foreach ($this->configuredAdapters($enabledSources) as $adapter) {
			$name = $adapter->getName();
			$cacheKey = $this->searchCacheKey($name, $mpn, $limit);
			if (!$this->bypassCache) {
				$cached = $this->cacheLookup($cacheKey);
				if ($cached !== null) {
					$results[$name] = $cached;
					continue;
				}
			}
			try {
				$pending[$name] = [$adapter, $adapter->searchByMpnAsync($mpn, $limit), $cacheKey];
			} catch (\Throwable $e) {
				$results[$name] = ['error' => 'dispatch: ' . $e->getMessage()];
			}
		}
		foreach ($pending as $name => [$adapter, $responses, $cacheKey]) {
			try {
				$mapped = $adapter->mapSearchByMpnResponses($responses, $mpn, $limit);
				$this->aggregatorCache->delete($cacheKey);
				$this->aggregatorCache->get($cacheKey, static fn(CacheItemInterface $item) => $mapped);
				$results[$name] = $mapped;
			} catch (\Throwable $e) {
				$results[$name] = ['error' => $e->getMessage()];
			}
		}
		return $results;
	}

	private function searchCacheKey(string $provider, string $mpn, int $limit): string
	{
		return 'search.' . $provider . '.' . hash('xxh3', $mpn . '|' . $limit);
	}

	private function detailCacheKey(string $provider, string $sourceSku): string
	{
		return 'detail.' . $provider . '.' . hash('xxh3', $sourceSku);
	}

	private function exactCacheKey(string $provider, string $mpn): string
	{
		return 'exact.' . $provider . '.' . hash('xxh3', $mpn);
	}

	/**
	 * PSR-6 lookup that returns the cached payload typed as mixed so
	 * callers can `instanceof`-narrow themselves. The Symfony
	 * CacheInterface `get($key, fn() => null)` shape phpstan infers as
	 * "always null" (callback's return type), forcing @var overrides
	 * that strict-rules forbids. Speaking to PSR-6 directly avoids the
	 * inference trap — the aggregatorCache adapter implements both.
	 */
	private function cacheLookup(string $key): mixed
	{
		if (!$this->aggregatorCache instanceof CacheItemPoolInterface) {
			return null;
		}
		$item = $this->aggregatorCache->getItem($key);
		return $item->isHit() ? $item->get() : null;
	}

	/**
	 * Phase 2 detail fetch for a single provider+sku. Adapter implements this
	 * via the same async dispatch/map pair as the bulk Phase 2 path — single
	 * provider here, but if adapter fans out internally (TME does), those
	 * internal calls still run in parallel.
	 */
	public function getDetails(string $providerName, string $sourceSku): ?InfoProviderResult
	{
		foreach ($this->adapters as $adapter) {
			if ($adapter->getName() === $providerName) {
				return $adapter->isConfigured() ? $adapter->getDetails($sourceSku) : null;
			}
		}
		return null;
	}

	/**
	 * Two-phase light path: Phase 1 only, grouped + light-merged
	 *
	 * Returns the same `AggregatedPartCandidate` shape as the full merge but
	 * with empty `parameters` and no pricing in `providerSpecific` — enough
	 * for the FE to render the candidates grid (mfr / MPN / sources /
	 * thumbnail / lifecycle / stock / package). The FE then calls
	 * {@see self::deepenCandidate()} for the row the user selects, which
	 * does the heavy Phase-2 fetch.
	 *
	 * Savings vs `searchByMpnAndMerge()`: skips the per-(source, sku) detail
	 * batch + the completion pass. A 1N4148 search across 5 sources drops
	 * from ~50 detail HTTP calls to ~5 (one search per source).
	 *
	 * No completion pass on purpose — completion's value is filling in
	 * missing sources to enable cross-source price comparison + richer
	 * parameter coverage, both of which are Phase-2 concerns. Light mode
	 * doesn't render those columns so the wallclock cost isn't worth it.
	 *
	 * @return AggregatedPartCandidate[]
	 */
	public function searchByMpnAndMergeLight(string $mpn, ?array $enabledSources = null, int $limit = 10): array
	{
		$light = $this->searchByMpn($mpn, $enabledSources, $limit);

		/** @var array<string, array<string, InfoProviderSearchResult>> $groups */
		$groups = [];
		foreach ($light as $source => $list) {
			if (isset($list['error'])) {
				continue;
			}
			foreach ($list as $sr) {
				if (!$sr instanceof InfoProviderSearchResult) {
					continue;
				}
				$key = $this->merger->groupKey($sr);
				if ($key === null) {
					continue;
				}
				$groups[$key][$source] ??= $sr;
			}
		}

		$candidates = [];
		foreach ($groups as $rowsBySource) {
			$candidates[] = $this->merger->mergeGroupLight($rowsBySource);
		}

		// Same sort the heavy path uses — exact match first, then by
		// contributing-source count. Operator expectations match across modes.
		$isExact = static function (AggregatedPartCandidate $c) use ($mpn): int {
			return strcasecmp($c->manufacturerPartNumber->chosenValue ?? '', $mpn) === 0 ? 1 : 0;
		};
		usort($candidates, static function (AggregatedPartCandidate $a, AggregatedPartCandidate $b) use ($isExact): int {
			$ae = $isExact($a);
			$be = $isExact($b);
			if ($ae !== $be) {
				return $be - $ae;
			}
			return count($b->contributingSources) <=> count($a->contributingSources);
		});

		// ExistingPart annotation only needs canonical (mfr, MPN) — both
		// already present in the light shell. One SQL hit for the batch.
		$this->existingPartFinder->annotate($candidates);

		return $candidates;
	}

	/**
	 * Deepen a single light candidate — Phase 2 fetch for the (source, sku)
	 * pairs handed in, merge into a heavy AggregatedPartCandidate, then run
	 * the parameter normalisation + value parser stages so the FE Apply Data
	 * dialog has structured fields.
	 *
	 * @param array<string, string> $sourceSkuMap source name => sourceSku
	 *        from the light candidate's `providerSpecific[*].sourceSku`
	 */
	public function deepenCandidate(array $sourceSkuMap): ?AggregatedPartCandidate
	{
		if ($sourceSkuMap === []) {
			return null;
		}
		$adaptersByName = $this->adaptersByName();

		// Bucket the SKUs per source for batched dispatch — same primitive
		// the heavy search path uses, just for ONE group instead of all
		$pendingPerSource = [];
		$cachedBySource = [];
		foreach ($sourceSkuMap as $source => $sku) {
			$adapter = $adaptersByName[$source] ?? null;
			if ($adapter === null || !$adapter->isConfigured()) {
				continue;
			}
			if (!$this->bypassCache) {
				$cached = $this->cacheLookup($this->detailCacheKey($source, $sku));
				if ($cached instanceof InfoProviderResult) {
					$cachedBySource[$source] = $cached;
					continue;
				}
			}
			try {
				$pendingPerSource[$source] = [$adapter, [$sku], $adapter->getDetailsBatchAsync([$sku])];
			} catch (\Throwable) {
				continue;
			}
		}

		$fullBySource = $cachedBySource;
		foreach ($pendingPerSource as $source => [$adapter, $skus, $responses]) {
			try {
				$mapped = $adapter->mapDetailsBatchResponses($responses, $skus);
			} catch (\Throwable) {
				$mapped = [];
			}
			foreach ($mapped as $sku => $result) {
				if ($result instanceof InfoProviderResult) {
					$cacheKey = $this->detailCacheKey($source, $sku);
					$this->aggregatorCache->delete($cacheKey);
					$this->aggregatorCache->get($cacheKey, static fn(CacheItemInterface $item) => $result);
					$fullBySource[$source] = $result;
				}
			}
		}

		if ($fullBySource === []) {
			return null;
		}
		$candidate = $this->merger->mergeGroup($fullBySource);

		foreach ($candidate->parameters as $source => $params) {
			foreach ($params as $p) {
				if ($p->canonicalName === null) {
					$p->canonicalName = $this->parameterNormalizer->canonicalize($p->rawName, $source);
				}
				$this->parameterValueParser->parse($p);
			}
		}

		// Annotate so the FE keeps the "already in inventory" badge on the deepened row too
		$this->existingPartFinder->annotate([$candidate]);

		return $candidate;
	}

	/**
	 * End-to-end: light search → group by canonical (mfr, MPN) → upgrade one
	 * detail per provider per group → merge into AggregatedPartCandidate
	 *
	 * @return AggregatedPartCandidate[]
	 */
	public function searchByMpnAndMerge(string $mpn, ?array $enabledSources = null, int $limit = 10, int $completionCap = self::COMPLETION_AUTO_CAP): array
	{
		$light = $this->searchByMpn($mpn, $enabledSources, $limit);

		// Group light SearchResult by (canonical mfr, MPN); keep first SKU per (group, source)
		/** @var array<string, array<string, InfoProviderSearchResult>> $groups */
		$groups = [];
		foreach ($light as $source => $list) {
			if (isset($list['error'])) {
				continue;
			}
			foreach ($list as $sr) {
				if (!$sr instanceof InfoProviderSearchResult) {
					continue;
				}
				$key = $this->merger->groupKey($sr);
				if ($key === null) {
					continue;
				}
				$groups[$key][$source] ??= $sr;
			}
		}

		// Phase 2 dispatch — batched per source. We collect every SKU the
		// merger asked for per provider and hand them in one shot to the
		// adapter; adapters with multi-symbol endpoints (TME) collapse N HTTP
		// calls into 1 per endpoint. Single-symbol adapters (Farnell, DigiKey)
		// fan out internally with curl_multi pipelining. Net effect for TME:
		// 21 simultaneous calls → 3 (sidesteps their ~10 rps rate limit and
		// the 429s we were getting).
		$adaptersByName = $this->adaptersByName();
		$skusPerSource = [];   // source => unique SKUs requested
		foreach ($groups as $rowsBySource) {
			foreach ($rowsBySource as $source => $sr) {
				$skusPerSource[$source][$sr->sourceSku] = true;
			}
		}

		// Per-(provider, sku) cache first. Any SKU already cached doesn't go
		// to the network; only the misses get batched-dispatched.
		$resultsBySourceBySku = []; // source => sku => ?InfoProviderResult
		$pendingPerSource = []; // source => [adapter, missingSkus[], responses]
		foreach ($skusPerSource as $source => $skuSet) {
			$adapter = $adaptersByName[$source] ?? null;
			if ($adapter === null) {
				continue;
			}
			$missing = [];
			foreach (array_keys($skuSet) as $sku) {
				if (!$this->bypassCache) {
					$cached = $this->cacheLookup($this->detailCacheKey($source, $sku));
					if ($cached instanceof InfoProviderResult) {
						$resultsBySourceBySku[$source][$sku] = $cached;
						continue;
					}
				}
				$missing[] = $sku;
			}
			if ($missing === []) {
				continue;
			}
			try {
				$responses = $adapter->getDetailsBatchAsync($missing);
			} catch (\Throwable) {
				continue;
			}
			$pendingPerSource[$source] = [$adapter, $missing, $responses];
		}

		// Drain — at this point every dispatched request is in-flight via
		// curl_multi. Mapping per source awaits its own batch and populates
		// the per-(source, sku) cache.
		foreach ($pendingPerSource as $source => [$adapter, $skus, $responses]) {
			try {
				$mapped = $adapter->mapDetailsBatchResponses($responses, $skus);
			} catch (\Throwable) {
				$mapped = [];
			}
			foreach ($mapped as $sku => $result) {
				if ($result instanceof InfoProviderResult) {
					$cacheKey = $this->detailCacheKey($source, $sku);
					$this->aggregatorCache->delete($cacheKey);
					$this->aggregatorCache->get($cacheKey, static fn(CacheItemInterface $item) => $result);
				}
				$resultsBySourceBySku[$source][$sku] = $result;
			}
		}

		$candidates = [];
		$fullBySourceByKey = []; // groupKey => [source => InfoProviderResult] — preserved for re-merge after completion
		$keyByCandidateId = []; // spl_object_id(candidate) => groupKey
		foreach ($groups as $key => $rowsBySource) {
			$fullBySource = [];
			foreach ($rowsBySource as $source => $sr) {
				$full = $resultsBySourceBySku[$source][$sr->sourceSku] ?? null;
				if ($full !== null) {
					$fullBySource[$source] = $full;
				}
			}
			if ($fullBySource !== []) {
				$candidate = $this->merger->mergeGroup($fullBySource);
				$candidates[] = $candidate;
				$fullBySourceByKey[$key] = $fullBySource;
				$keyByCandidateId[spl_object_id($candidate)] = $key;
			}
		}

		// Completion pass — exact-MPN re-fetch at missing sources for the top
		// candidates. Per-vendor keyword sort means a popular MPN can land top-1
		// at one distributor and not appear at all at another in the same query;
		// this pass undoes that asymmetry so cross-source price comparison and
		// ExistingPartFinder match work against the fullest possible candidate.
		$candidates = $this->completionPass(
			$candidates,
			$fullBySourceByKey,
			$keyByCandidateId,
			$enabledSources,
			$adaptersByName,
			$completionCap
		);

		// Two-level sort:
		//  1. Exact MPN match comes first. Ambiguous queries (e.g. "FG-06"
		//     returns Sunon fan grilles AND microchip "FG06xx" fuzzy hits)
		//     would otherwise see fuzzy matches outrank the real thing
		//     whenever they happen to be carried by more distributors.
		//  2. Within each bucket, more contributing sources = higher (= more
		//     "this part is real" votes).
		$isExact = static function (AggregatedPartCandidate $c) use ($mpn): int {
			return strcasecmp($c->manufacturerPartNumber->chosenValue ?? '', $mpn) === 0 ? 1 : 0;
		};
		usort($candidates, static function (AggregatedPartCandidate $a, AggregatedPartCandidate $b) use ($isExact): int {
			$ae = $isExact($a);
			$be = $isExact($b);
			if ($ae !== $be) {
				return $be - $ae;
			}
			return count($b->contributingSources) <=> count($a->contributingSources);
		});

		// Flag candidates that already live in the local Part inventory so the
		// UI can show "✓ already in inventory: N pcs". One SQL hit total.
		$this->existingPartFinder->annotate($candidates);

		// Stage 1 — Normalise every parameter's rawName → canonical via the
		// alias table. Done post-merge so all sources go through the same
		// normalizer in one place; per-adapter normalization would scatter
		// the same logic.
		// Stage 2 — Parse rawValue into structured numeric + unit + SI prefix,
		// and lift `(Max)/(Min)/(Typ)` suffixes off the canonical name onto
		// the `qualifier` field. The frontend's Apply Data then routes
		// numeric values to PartParameter.value/minValue/maxValue + sets the
		// Unit + SiPrefix FKs instead of dumping everything into stringValue.
		foreach ($candidates as $candidate) {
			foreach ($candidate->parameters as $source => $params) {
				foreach ($params as $p) {
					if ($p->canonicalName === null) {
						$p->canonicalName = $this->parameterNormalizer->canonicalize($p->rawName, $source);
					}
					$this->parameterValueParser->parse($p);
				}
			}
		}

		return $candidates;
	}

	/**
	 * Post-merge completion: take candidates that ended up with fewer than the
	 * total enabled-source count, ask the missing sources to confirm via a
	 * STRICT (manuPartNum, mpns[], filtered keyword) lookup, and inject any
	 * exact matches back into the per-source map for a re-merge.
	 *
	 * Why this exists:
	 *  - Phase-1 search at each distributor is sorted by THEIR relevance.
	 *    Popular MPNs (LM7805, 1N4148) routinely show TI variant at the top of
	 *    DigiKey but STMicro variant at the top of Farnell — neither's top-N
	 *    contains the other's variant. After group-by-(canonical-mfr,MPN) the
	 *    candidate ends up with one source even though every distributor
	 *    actually stocks it.
	 *  - Without this pass, ExistingPartFinder match-rate and cross-source
	 *    price comparison are weakened. With it, candidate source coverage
	 *    becomes deterministic per MPN.
	 *
	 * Cost-bounded by `COMPLETION_AUTO_CAP` (= 10 candidates). Everything is
	 * cached at three levels:
	 *  - per-(provider, mpn) exact-search result (warm cache → zero HTTP),
	 *  - per-(provider, sku) detail cache (shared with Phase-2 batched flow),
	 *  - merger output is recomputed but cheap.
	 *
	 * @param AggregatedPartCandidate[] $candidates
	 * @param array<string, array<string, InfoProviderResult>> $fullBySourceByKey groupKey => per-source map (mutated)
	 * @param array<int, string> $keyByCandidateId spl_object_id(candidate) => groupKey
	 * @param array<string>|null $enabledSources
	 * @param array<string, InfoProviderInterface> $adaptersByName
	 * @return AggregatedPartCandidate[]
	 */
	private function completionPass(
		array  $candidates,
		array  $fullBySourceByKey,
		array  $keyByCandidateId,
		?array $enabledSources,
		array  $adaptersByName,
		int    $completionCap
	): array
	{
		if ($candidates === []) {
			return $candidates;
		}

		// All sources that were eligible for THIS query. A candidate is
		// "complete" when its contributingSources == this set.
		$configuredSources = [];
		foreach ($this->configuredAdapters($enabledSources) as $adapter) {
			$configuredSources[] = $adapter->getName();
		}
		if (count($configuredSources) < 2) {
			// Nothing to complete from with a single source.
			return $candidates;
		}

		// Pick incomplete candidates in input order, capped — we have not
		// sorted yet, so input order is roughly group-creation order, which
		// is fine for "top-N most-likely candidates" since sort is
		// (isExact DESC, contributingSources DESC) and incomplete-by-definition
		// have fewer contributingSources, so they sit lower anyway after sort.
		// Capping here keeps the worst-case HTTP fan-out bounded.
		$incomplete = [];   // each: [$idxInCandidates, $candidate, $missingSources[]]
		foreach ($candidates as $i => $c) {
			if ($completionCap >= 0 && count($incomplete) >= $completionCap) {
				break;
			}
			$missing = array_values(array_diff($configuredSources, $c->contributingSources));
			if ($missing === []) {
				continue;
			}
			$incomplete[] = [$i, $c, $missing];
		}
		if ($incomplete === []) {
			return $candidates;
		}

		// Phase A — dispatch exact-MPN lookups for (source × MPN) pairs.
		// De-dup by (source, mpn) so two candidates that share an MPN but
		// differ on manufacturer (e.g. LM7805 from TI vs STMicro) only fire
		// one HTTP request per source.
		$exactPending = [];   // "source|mpn" => [adapter, responses, cacheKey, mpn]
		$exactCached = [];    // "source|mpn" => InfoProviderSearchResult[]
		foreach ($incomplete as [$_i, $c, $missing]) {
			$mpn = $c->manufacturerPartNumber->chosenValue ?? '';
			if ($mpn === '') {
				continue;
			}
			foreach ($missing as $source) {
				$dedupKey = $source . '|' . $mpn;
				if (isset($exactPending[$dedupKey]) || isset($exactCached[$dedupKey])) {
					continue;
				}
				$cacheKey = $this->exactCacheKey($source, $mpn);
				if (!$this->bypassCache) {
					$cached = $this->cacheLookup($cacheKey);
					if ($cached !== null) {
						$exactCached[$dedupKey] = $cached;
						continue;
					}
				}
				$adapter = $adaptersByName[$source] ?? null;
				if ($adapter === null) {
					continue;
				}
				try {
					$responses = $adapter->searchExactByMpnAsync($mpn);
				} catch (\Throwable) {
					continue;
				}
				$exactPending[$dedupKey] = [$adapter, $responses, $cacheKey, $mpn];
			}
		}

		// Drain Phase A — every dispatched request is in-flight via curl_multi.
		// Mapping per pair awaits and warms the per-(source, mpn) exact cache.
		foreach ($exactPending as $dedupKey => [$pendAdapter, $responses, $cacheKey, $mpn]) {
			try {
				$hits = $pendAdapter->mapSearchExactByMpnResponses($responses, $mpn);
			} catch (\Throwable) {
				$hits = [];
			}
			$this->aggregatorCache->delete($cacheKey);
			$this->aggregatorCache->get($cacheKey, static fn(CacheItemInterface $item) => $hits);
			$exactCached[$dedupKey] = $hits;
		}

		// Phase B — match hits to candidates via merger->groupKey() (same
		// canonical (mfr, MPN) the original grouping used). Take the first
		// hit per (candidate, source) since a single distributor can list one
		// MPN under multiple SKUs (different packaging/quantities); the
		// merger only consumes one per source anyway.
		$newSkusPerSource = []; // source => [sku => true]
		$skuClaimsBySource = []; // source => sku => [[candidateIdx, candidateKey], ...]
		foreach ($incomplete as [$idx, $c, $missing]) {
			$mpn = $c->manufacturerPartNumber->chosenValue ?? '';
			if ($mpn === '') {
				continue;
			}
			$candidateKey = $keyByCandidateId[spl_object_id($c)] ?? null;
			if ($candidateKey === null) {
				continue;
			}
			foreach ($missing as $source) {
				$hits = $exactCached[$source . '|' . $mpn] ?? [];
				foreach ($hits as $hit) {
					if ($this->merger->groupKey($hit) !== $candidateKey) {
						continue;
					}
					$sku = $hit->sourceSku;
					if ($sku === '') {
						continue;
					}
					$newSkusPerSource[$source][$sku] = true;
					$skuClaimsBySource[$source][$sku][] = [$idx, $candidateKey];
					break;
				}
			}
		}
		if ($newSkusPerSource === []) {
			return $candidates;
		}

		// Phase C — batched detail fetch for the SKUs we just discovered.
		// Reuses the same per-(provider, sku) cache as the main Phase-2 flow,
		// so a SKU previously fetched within the TTL costs zero.
		$detailsBySourceBySku = []; // source => sku => InfoProviderResult
		$pendingDetails = []; // source => [adapter, missingSkus, responses]
		foreach ($newSkusPerSource as $source => $skuSet) {
			$adapter = $adaptersByName[$source] ?? null;
			if ($adapter === null) {
				continue;
			}
			$missingSkus = [];
			foreach (array_keys($skuSet) as $sku) {
				if (!$this->bypassCache) {
					$cached = $this->cacheLookup($this->detailCacheKey($source, $sku));
					if ($cached instanceof InfoProviderResult) {
						$detailsBySourceBySku[$source][$sku] = $cached;
						continue;
					}
				}
				$missingSkus[] = $sku;
			}
			if ($missingSkus === []) {
				continue;
			}
			try {
				$responses = $adapter->getDetailsBatchAsync($missingSkus);
			} catch (\Throwable) {
				continue;
			}
			$pendingDetails[$source] = [$adapter, $missingSkus, $responses];
		}
		foreach ($pendingDetails as $source => [$pendAdapter, $skus, $responses]) {
			try {
				$mapped = $pendAdapter->mapDetailsBatchResponses($responses, $skus);
			} catch (\Throwable) {
				$mapped = [];
			}
			foreach ($mapped as $sku => $result) {
				if (!$result instanceof InfoProviderResult) {
					continue;
				}
				$cacheKey = $this->detailCacheKey($source, $sku);
				$this->aggregatorCache->delete($cacheKey);
				$this->aggregatorCache->get($cacheKey, static fn(CacheItemInterface $item) => $result);
				$detailsBySourceBySku[$source][$sku] = $result;
			}
		}

		// Phase D — inject the new per-source rows into the saved fullBySource
		// map and re-merge each touched candidate. We write back to the same
		// $candidates index slot so caller-side ordering is preserved (sort
		// runs immediately after this returns).
		$touchedKeys = []; // groupKey => candidateIdx
		foreach ($skuClaimsBySource as $source => $skuClaims) {
			foreach ($skuClaims as $sku => $claims) {
				$result = $detailsBySourceBySku[$source][$sku] ?? null;
				if (!$result instanceof InfoProviderResult) {
					continue;
				}
				foreach ($claims as [$idx, $key]) {
					if (isset($fullBySourceByKey[$key][$source])) {
						// Already contributed — guard against accidentally
						// stomping a real Phase-2 result with completion data
						// (shouldn't happen because we only fan out for
						// MISSING sources, but cheap to verify)
						continue;
					}
					$fullBySourceByKey[$key][$source] = $result;
					$touchedKeys[$key] = $idx;
				}
			}
		}
		foreach ($touchedKeys as $key => $idx) {
			$candidates[$idx] = $this->merger->mergeGroup($fullBySourceByKey[$key]);
		}

		return $candidates;
	}

	/**
	 * @return array<string> adapter names that are currently usable
	 */
	public function configuredSources(): array
	{
		$out = [];
		foreach ($this->adapters as $adapter) {
			if ($adapter->isConfigured()) {
				$out[] = $adapter->getName();
			}
		}
		return $out;
	}

	/**
	 * Walk every URL-handler-capable adapter, ask the first one whose
	 * `getHandledDomains()` matches the URL's host to extract
	 * `{mpn, manufacturer}` from the path. Query string and fragment
	 * are stripped before delegating so adapters only see the canonical
	 * path. Returns null when no adapter recognises the URL or none can
	 * pattern-match the path.
	 *
	 * @return array{mpn: string, manufacturer: string, source: string}|null
	 */
	public function resolveUrl(string $url): ?array
	{
		$host = parse_url($url, PHP_URL_HOST);
		if (!is_string($host) || $host === '') {
			return null;
		}
		$hostLc = strtolower($host);

		// Strip query string + fragment so adapters get a clean canonical
		// path to regex against. Tracking params (?qs=, ?mwid=, …) are
		// noise for our purposes.
		$scheme = parse_url($url, PHP_URL_SCHEME);
		if (!is_string($scheme) || $scheme === '') {
			$scheme = 'https';
		}
		$path = parse_url($url, PHP_URL_PATH);
		if (!is_string($path) || $path === '') {
			$path = '/';
		}
		$cleanUrl = $scheme . '://' . $host . $path;

		foreach ($this->urlHandlers as $handler) {
			$matches = false;
			foreach ($handler->getHandledDomains() as $d) {
				$dLc = strtolower($d);
				if ($hostLc === $dLc || str_ends_with($hostLc, '.' . $dLc)) {
					$matches = true;
					break;
				}
			}
			if (!$matches) {
				continue;
			}
			$extracted = $handler->tryExtractFromURL($cleanUrl);
			if ($extracted === null) {
				continue;
			}
			$source = $handler->getName();
			$mpn = $extracted['mpn'];
			$mfr = $extracted['manufacturer'];
			$sku = $extracted['sourceSku'] ?? '';

			// SourceSku-only case (LCSC, future Cxxxxx-style URLs): the
			// URL doesn't carry mpn+manufacturer, only the distributor's
			// own part code. Ask the matching adapter to resolve sku →
			// full detail and fill in the missing fields. One extra HTTP
			// call upfront, but the standard MPN search needs mpn to
			// run at all. Cached after first hit per (source, sku) by
			// `getDetails()` itself.
			if ($mpn === '' && $sku !== '') {
				$detail = $this->getDetails($source, $sku);
				if ($detail !== null) {
					$mpn = $detail->manufacturerPartNumber;
					if ($mfr === '') $mfr = $detail->manufacturerName;
				}
			}

			$out = ['manufacturer' => $mfr, 'mpn' => $mpn, 'source' => $source];
			if ($sku !== '') {
				$out['sourceSku'] = $sku;
			}
			return $out;
		}
		return null;
	}

	/**
	 * @return array<int, array{name: string, configured: bool, capabilities: array<int, string>}>
	 */
	public function sourcesWithCapabilities(): array
	{
		$out = [];
		foreach ($this->adapters as $adapter) {
			$out[] = [
				'name' => $adapter->getName(),
				'configured' => $adapter->isConfigured(),
				'capabilities' => array_map(static fn(ProviderCapability $c) => $c->value, $adapter->getCapabilities())
			];
		}
		return $out;
	}

	/**
	 * @param array<string>|null $enabledSources
	 * @return iterable<InfoProviderInterface>
	 */
	private function configuredAdapters(?array $enabledSources): iterable
	{
		foreach ($this->adapters as $adapter) {
			$name = $adapter->getName();
			if ($enabledSources !== null && !in_array($name, $enabledSources, true)) {
				continue;
			}
			if (!$adapter->isConfigured()) {
				continue;
			}
			yield $adapter;
		}
	}

	/**
	 * @return array<string, InfoProviderInterface>
	 */
	private function adaptersByName(): array
	{
		$map = [];
		foreach ($this->adapters as $adapter) {
			$map[$adapter->getName()] = $adapter;
		}
		return $map;
	}
}
