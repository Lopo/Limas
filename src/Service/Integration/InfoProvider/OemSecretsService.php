<?php

namespace Limas\Service\Integration\InfoProvider;

use Nette\Utils\Json;
use Psr\Cache\CacheItemInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;


/**
 * Thin client over the OEMSecrets Part Search API
 * (https://oemsecretsapi.com/documentation)
 *
 * One endpoint:
 *   GET https://oemsecretsapi.com/partsearch
 *       ?apiKey=…&searchTerm=<MPN>&currency=<ISO>&countryCode=<ISO2>
 *
 * Response: { version, status, search_term, country_code, parts_returned,
 *             stock: [ { manufacturer, part_number, distributor: {…},
 *                        quantity_in_stock, prices: {currency: ladder[]}, … } ] }
 *
 * OEMSecrets is itself a meta-aggregator — one call returns rows from
 * dozens of downstream distributors. The adapter therefore filters out
 * sources we already query directly (DigiKey/Mouser/TME/Farnell/Newark)
 * and keeps the rest as "novel" distributor coverage.
 *
 * Caching:
 *  - `search_{xxh3(mpn|currency|country)}` — the raw response (5 min TTL,
 *    same as aggregator-level cache so the two layers stay coherent).
 *  - `part_{sku}` — per-entry slice, populated when the adapter awaits the
 *    search response. Lets Phase-2 detail fetch read from cache without a
 *    second HTTP call — important for OEMSecrets specifically because
 *    their testing tier ships with a 10-req/day quota.
 */
class OemSecretsService
{
	private const string OEMSECRETS_ENDPOINT = 'https://oemsecretsapi.com/partsearch';


	public function __construct(
		private readonly HttpClientInterface           $httpClient,
		private readonly CacheInterface                $oemsecretsCache,
		#[\SensitiveParameter] private readonly string $apiKey,
		private readonly string                        $currency = 'EUR',
		private readonly string                        $countryCode = 'SK'
	)
	{
	}

	public function isConfigured(): bool
	{
		return $this->apiKey !== '';
	}

	/**
	 * Phase-1 async dispatch. Each call is its own HTTP request — the
	 * adapter awaits and caches the per-part slices on the way back so
	 * Phase-2 detail fetches can read from local cache without a second
	 * round-trip (= zero HTTP cost on detail).
	 */
	public function searchByMpnAsync(string $mpn): ResponseInterface
	{
		return $this->httpClient->request('GET', self::OEMSECRETS_ENDPOINT, [
			'query' => [
				'apiKey' => $this->apiKey,
				'searchTerm' => $mpn,
				'currency' => $this->currency,
				'countryCode' => $this->countryCode
			],
			'headers' => ['Accept' => 'application/json']
		]);
	}

	/**
	 * Await a lazy response, decode JSON, and prime the per-part cache from
	 * its `stock[]` entries. Mirrors `FarnellService::awaitAndCache` so the
	 * adapter pipeline (search-then-batch-detail) sees a coherent picture.
	 *
	 * Returns the full top-level decoded array — adapters use `$data['stock']`
	 * for grouping / picking and the meta keys (parts_returned, status) for
	 * sanity checks.
	 *
	 * @return array{
	 *     version?: string,
	 *     status?: string,
	 *     search_term?: string,
	 *     parts_returned?: int|string,
	 *     stock?: array<int, array<string, mixed>>
	 * }
	 */
	public function awaitAndCache(ResponseInterface $response): array
	{
		// OEMSecrets returns one 401 for both "invalid key" and "quota exhausted"
		// (their own message literally says "User is not accepted OR has run
		// out of api calls" — they don't tell us which). Without detection
		// we'd fall through to `stock ?? []` and the aggregator UI would show
		// "0 results" — same as "MPN not found", which is misleading. Surface
		// as exception so the per-source error panel shows the real reason.
		if ($response->getStatusCode() === 401) {
			throw new \RuntimeException('OEMSecrets: API key rejected or daily quota exhausted (HTTP 401)');
		}
		$data = $response->toArray(false);
		foreach (($data['stock'] ?? []) as $part) {
			// OEMSecrets sometimes returns the same MPN under multiple
			// distributor entries with the SAME source_part_number; key the
			// per-part cache by distributor too so we don't lose ladders
			$pn = (string)($part['part_number'] ?? '');
			$common = (string)($part['distributor']['distributor_common_name'] ?? '');
			$dist = $common !== '' ? $common : (string)($part['distributor']['distributor_name'] ?? '');
			if ($pn === '' || $dist === '') {
				continue;
			}
			$sku = $this->cacheKeyFor($pn, $dist);
			$this->oemsecretsCache->delete($sku);
			$this->oemsecretsCache->get($sku, static fn(CacheItemInterface $item) => Json::encode($part));
		}
		return $data;
	}

	/**
	 * Synthetic SKU used as `sourceSku` on InfoProviderSearchResult so
	 * Phase-2 batched detail fetch can identify the part without another
	 * HTTP call. Format: `<part_number>|<distributor_common_name>` —
	 * unique within OEMSecrets's result set per (part, distributor) pair.
	 */
	public function cacheKeyFor(string $partNumber, string $distributorCommonName): string
	{
		return 'part_' . hash('xxh3', $partNumber . '|' . $distributorCommonName);
	}

	/**
	 * Phase-2 read — adapter passes a synthetic sku coined from
	 * cacheKeyFor() at search time. Returns null when the entry was
	 * never cached (e.g. the OEMSecrets search was rerun and trimmed
	 * the entry out, or TTL expired between phase 1 and phase 2).
	 *
	 * @return array<string, mixed>|null
	 */
	public function getCachedPart(string $cacheKey): ?array
	{
		// PSR-6 path — see comment in LcscService::getCachedDetail
		if (!$this->oemsecretsCache instanceof \Psr\Cache\CacheItemPoolInterface) {
			return null;
		}
		$item = $this->oemsecretsCache->getItem($cacheKey);
		if (!$item->isHit()) {
			return null;
		}
		$raw = $item->get();
		if (!is_string($raw) || $raw === '') {
			return null;
		}
		try {
			$decoded = Json::decode($raw, true);
		} catch (\Throwable) {
			return null;
		}
		return is_array($decoded) ? $decoded : null;
	}
}
