<?php

namespace Limas\Service\Integration\InfoProvider\Contract;

use Limas\Service\Integration\InfoProvider\Dto\InfoProviderResult;
use Limas\Service\Integration\InfoProvider\Dto\InfoProviderSearchResult;
use Limas\Service\Integration\InfoProvider\ProviderCapability;
use Symfony\Contracts\HttpClient\ResponseInterface;


/**
 * Common contract every InfoProvider adapter implements so the aggregator can
 * fan out a single MPN query across many sources and merge the results
 *
 * Two-phase model:
 *   1. `searchByMpn(mpn)` → light `InfoProviderSearchResult[]` for candidate listing.
 *   2. `getDetails(sourceSku)` → full `InfoProviderResult` with parameters + pricing
 *       once the user (or the aggregator merger) actually wants the detail.
 *
 * Adapters where the underlying API needs separate calls for parameter / price
 * data (e.g. TME) realise the search/detail split as a real HTTP saving.
 * Adapters whose search response already contains everything (DigiKey V4,
 * Farnell) may just slice the same payload differently for the two methods.
 */
interface InfoProviderInterface
{
	/**
	 * Short provider identifier — 'tme', 'digikey', 'farnell', 'mouser', 'octopart'.
	 */
	public function getName(): string;

	/**
	 * True when the underlying service has all credentials it needs to run.
	 * Aggregator uses this to filter out providers with missing keys.
	 */
	public function isConfigured(): bool;

	/**
	 * What kinds of data this provider can supply. Informational only — used
	 * by UI/REST callers to tell users what to expect from this source. The
	 * aggregator does not gate runtime behaviour on these flags.
	 *
	 * @return ProviderCapability[]
	 */
	public function getCapabilities(): array;

	/**
	 * Phase 1 — light search by MPN.
	 *
	 * @return InfoProviderSearchResult[] empty array if no match
	 */
	public function searchByMpn(string $mpn, int $limit = 10): array;

	/**
	 * Phase 2 — heavy detail fetch. `$sourceSku` is the SKU value carried in
	 * the light SearchResult, e.g. the TME `symbol`, DigiKey `DigiKeyProductNumber`,
	 * Farnell `sku`. Returns null when the SKU is no longer available at the
	 * provider (deleted/obsoleted).
	 */
	public function getDetails(string $sourceSku): ?InfoProviderResult;

	/**
	 * Async dispatch of Phase 1. Returns a map of label → lazy
	 * `ResponseInterface`. The aggregator collects these from every provider
	 * before awaiting any so curl_multi can pipeline the requests in parallel.
	 *
	 * Most adapters need a single HTTP call here and will return one element.
	 * Adapters that fan out internally (e.g. TME's 3-call detail flow on
	 * Phase 2) return multiple — labels are adapter-private, only the matching
	 * `mapSearchByMpnResponses` knows what to do with them.
	 *
	 * @return array<string, ResponseInterface>
	 */
	public function searchByMpnAsync(string $mpn, int $limit = 10): array;

	/**
	 * Sync mapping side of the async pair. Receives whatever
	 * `searchByMpnAsync` produced; awaits the responses and returns the
	 * standard light DTO list.
	 *
	 * @param array<string, ResponseInterface> $responses
	 * @return InfoProviderSearchResult[]
	 */
	public function mapSearchByMpnResponses(array $responses, string $mpn, int $limit = 10): array;

	/**
	 * Async dispatch of Phase 2. Same shape as searchByMpnAsync — typically a
	 * single response for Farnell/DigiKey, multiple for TME (which needs
	 * /products + /products/parameters + /products/data)
	 *
	 * @return array<string, ResponseInterface>
	 */
	public function getDetailsAsync(string $sourceSku): array;

	/**
	 * @param array<string, ResponseInterface> $responses
	 */
	public function mapDetailsResponses(array $responses, string $sourceSku): ?InfoProviderResult;

	/**
	 * Batched Phase 2 dispatch — used by the aggregator when it knows it will
	 * fetch details for many SKUs from the same provider in one go. Adapters
	 * whose APIs accept a list of identifiers (TME's `/products?symbols[]=…`)
	 * can collapse N HTTP calls into 1 per endpoint, dodging per-second rate
	 * limits that would otherwise 429 us out (TME caps at ~10 rps).
	 *
	 * Default fan-out (per-sku) is fine for Farnell/DigiKey — curl_multi still
	 * pipelines them, just doesn't batch them server-side.
	 *
	 * @param array<string> $sourceSkus unique SKUs
	 * @return array<string, ResponseInterface>  opaque label-map; the matching
	 *         `mapDetailsBatchResponses` knows how to read it
	 */
	public function getDetailsBatchAsync(array $sourceSkus): array;

	/**
	 * Sync mapping side of the batch dispatch
	 *
	 * @param array<string, ResponseInterface> $responses
	 * @param array<string> $sourceSkus
	 * @return array<string, ?InfoProviderResult>  sku => result (null if not in response)
	 */
	public function mapDetailsBatchResponses(array $responses, array $sourceSkus): array;

	/**
	 * STRICT per-MPN lookup — used by the aggregator's completion pass to fill
	 * in candidates that a vendor's keyword sort didn't surface in the initial
	 * Phase-1 search. Unlike `searchByMpnAsync` (a relevance-sorted keyword
	 * search where the exact MPN may not rank in the top-N), this hits the
	 * adapter's strict endpoint:
	 *   - Farnell: `term=manuPartNum:<MPN>`
	 *   - TME:     `/products?mpns[]=<MPN>`
	 *   - DigiKey: keyword + post-filter on `ManufacturerProductNumber`
	 *
	 * Returns 0..N exact matches across manufacturers. The aggregator decides
	 * which one belongs to which candidate based on canonical mfr name.
	 *
	 * @return array<string, ResponseInterface>
	 */
	public function searchExactByMpnAsync(string $mpn): array;

	/**
	 * Sync mapping side of the strict-MPN dispatch. Filters down to entries
	 * whose MPN equals `$mpn` (case-insensitive) — adapters that share an
	 * endpoint with the fuzzy keyword search need this filter to guarantee
	 * the "exact" semantics.
	 *
	 * @param array<string, ResponseInterface> $responses
	 * @return InfoProviderSearchResult[]
	 */
	public function mapSearchExactByMpnResponses(array $responses, string $mpn): array;
}
