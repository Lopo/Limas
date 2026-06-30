<?php

namespace Limas\Service\Integration\InfoProvider\Adapter;

use Limas\Service\Integration\InfoProvider\Contract\InfoProviderInterface;
use Limas\Service\Integration\InfoProvider\Dto\InfoProviderResult;
use Limas\Service\Integration\InfoProvider\Dto\InfoProviderSearchResult;
use Limas\Service\Integration\InfoProvider\Dto\Parameter;
use Limas\Service\Integration\InfoProvider\Dto\PriceBreak;
use Limas\Service\Integration\InfoProvider\Enum\ManufacturingStatus;
use Limas\Service\Integration\InfoProvider\ProviderCapability;
use Limas\Service\Integration\InfoProvider\TMEService;


/**
 * Adapts TME v2 API into normalised InfoProviderResult instances
 *
 * Pulls full info via three calls:
 *   - /products/search          — list of matches by phrase (top-N)
 *   - /products/parameters      — parameters per symbol
 *   - /products/data            — prices + stock per symbol (scope=prices,stock)
 *   - /products/files           — documents (datasheets) per symbol; `assets`
 *                                 from /products carries photos but not docs
 */
final class TmeAdapter
	implements InfoProviderInterface
{
	private const int TME_BATCH_CAP = 50;
	/**
	 * TME-specific product_status tags observed in v2 responses
	 *
	 * Mapped (manufacturer lifecycle signals):
	 *   - AVAILABLE_WHILE_STOCKS_LAST → EndOfLife (stock yes, no replenishment)
	 *   - ARCHIVED                    → Discontinued (handled by fromRaw substring)
	 *
	 * Intentionally left to Unknown (distributor stocking state, NOT lifecycle):
	 *   - NOT_IN_OFFER, CANNOT_BE_ORDERED, BLOCKED_FOR_ZBL_BACKORDERS — TME
	 *     stopped carrying this SKU; manufacturer may still produce it.
	 *   - HARDLY_AVAILABLE, PROMOTED — stock-low / marketing flags.
	 *   - NEW → Active (handled by fromRaw exact match).
	 *
	 * Mapped explicitly here because fromRaw is keyword-based and the lifecycle
	 * tag above doesn't contain a canonical substring ("eol", "discontinued")
	 */
	private const array TME_STATUS_MAP = [
		'AVAILABLE_WHILE_STOCKS_LAST' => ManufacturingStatus::EndOfLife
	];


	public function __construct(
		private readonly TMEService $service,
		private readonly string     $token = '',
		private readonly string     $appSecret = ''
	)
	{
	}

	public function getName(): string
	{
		return 'tme';
	}

	public function isConfigured(): bool
	{
		return $this->token !== '' && $this->appSecret !== '';
	}

	public function getCapabilities(): array
	{
		// search returns BASIC + PICTURE (assets.primary_photo).
		// /products/parameters → PARAMETERS, /products/data → PRICE,
		// /products/files → DATASHEET (documents[].type=DTE).
		// FOOTPRINT is mined from /products/parameters labels — "Case",
		// "Kind of package", etc. Only available on Phase-2 detail responses
		// (Phase-1 search payload has no parameters block), so the light
		// SearchResult leaves packageName null and detail filling in.
		// GTIN: TME returns `ean` on the product but it's often empty for components.
		return [
			ProviderCapability::BASIC,
			ProviderCapability::PICTURE,
			ProviderCapability::DATASHEET,
			ProviderCapability::PRICE,
			ProviderCapability::FOOTPRINT,
			ProviderCapability::PARAMETERS
		];
	}

	public function searchByMpn(string $mpn, int $limit = 10): array
	{
		return $this->mapSearchByMpnResponses($this->searchByMpnAsync($mpn, $limit), $mpn, $limit);
	}

	/**
	 * Phase-1 dispatches BOTH `/products/search?phrase=...` AND
	 * `/products?symbols[]=...`. Some products only exist under their TME
	 * symbol with `manufacturer_symbols: []` (e.g. BS-244DSM-R) and the
	 * phrase search misses them entirely; the direct symbol lookup catches
	 * them. Costs one extra request per Phase-1 query — TME's 50 rps cap is
	 * irrelevant at this volume.
	 */
	public function searchByMpnAsync(string $mpn, int $limit = 10): array
	{
		return [
			'search' => $this->service->searchByKeywordAsync($mpn),
			'symbol' => $this->service->getProductsAsync(symbols: [$mpn])
		];
	}

	public function mapSearchByMpnResponses(array $responses, string $mpn, int $limit = 10): array
	{
		// Note the envelope difference: `/products/search` wraps results
		// under `data.products.elements`, `/products` (mpns/symbols lookup)
		// returns `data.elements`. Dedup by `symbol` so a product hit by
		// both endpoints isn't double-counted.
		$seen = [];
		$out = [];

		$searchData = $this->safeArray($responses['search'] ?? null);
		foreach (array_slice($searchData['data']['products']['elements'] ?? [], 0, $limit) as $e) {
			$sym = (string)($e['symbol'] ?? '');
			if ($sym === '' || isset($seen[$sym])) {
				continue;
			}
			$seen[$sym] = true;
			$out[] = $this->mapLight($e, exactMpn: $mpn);
		}

		$symbolData = $this->safeArray($responses['symbol'] ?? null);
		foreach ($symbolData['data']['elements'] ?? [] as $e) {
			$sym = (string)($e['symbol'] ?? '');
			if ($sym === '' || isset($seen[$sym])) {
				continue;
			}
			$seen[$sym] = true;
			$out[] = $this->mapLight($e, exactMpn: $mpn);
		}

		return array_slice($out, 0, $limit);
	}

	public function getDetails(string $sourceSku): ?InfoProviderResult
	{
		return $this->mapDetailsResponses($this->getDetailsAsync($sourceSku), $sourceSku);
	}

	/**
	 * Four TME endpoints (`/products`, `/products/parameters`, `/products/data`,
	 * `/products/files`) fired together — curl_multi pipelines them so the
	 * per-detail wallclock collapses from sum-of-four to max-of-four.
	 */
	public function getDetailsAsync(string $sourceSku): array
	{
		return [
			'products' => $this->service->getProductsAsync(symbols: [$sourceSku]),
			'parameters' => $this->service->getProductParametersAsync([$sourceSku]),
			'data' => $this->service->getProductDataAsync([$sourceSku], [], ['prices', 'stock']),
			'files' => $this->service->getProductFilesAsync([$sourceSku])
		];
	}

	public function mapDetailsResponses(array $responses, string $sourceSku): ?InfoProviderResult
	{
		$products = $this->safeArray($responses['products']);
		$element = $products['data']['elements'][0] ?? null;
		if ($element === null) {
			return null;
		}
		$params = $this->indexBy($this->safeArray($responses['parameters']), 'symbol');
		$data = $this->indexBy($this->safeArray($responses['data']), 'symbol', basePath: 'data.elements');
		$files = $this->indexBy($this->safeArray($responses['files'] ?? null), 'symbol');
		return $this->mapFull($element, $params[$sourceSku] ?? null, $data[$sourceSku] ?? null, $files[$sourceSku] ?? null);
	}

	/**
	 * BATCHED — TME accepts up to 50 symbols per call. For >50 we chunk and
	 * fire all chunks in parallel via curl_multi (still respects TME's ~10 rps
	 * because we only emit `ceil(N/50) * 4` requests total — for N=100 that's 8).
	 *
	 * Critical optimisation that keeps us under TME's rate limit: previously
	 * 7 candidates × 4 endpoints fanned out into 28 simultaneous requests
	 * (429s). Now it's exactly `ceil(N/50) * 4` total.
	 *
	 * Returns a flat label map: each chunk's responses get distinct labels
	 * (`products.0`, `products.1`, …) so the mapping side can re-assemble.
	 */
	public function getDetailsBatchAsync(array $sourceSkus): array
	{
		if ($sourceSkus === []) {
			return [];
		}
		$out = [];
		foreach (array_chunk($sourceSkus, self::TME_BATCH_CAP) as $i => $chunk) {
			$out["products.$i"] = $this->service->getProductsAsync(symbols: $chunk);
			$out["parameters.$i"] = $this->service->getProductParametersAsync($chunk);
			$out["data.$i"] = $this->service->getProductDataAsync($chunk, [], ['prices', 'stock']);
			$out["files.$i"] = $this->service->getProductFilesAsync($chunk);
		}
		return $out;
	}

	/**
	 * Completion pass — fires `/products?mpns[]=...` AND `/products?symbols[]=...`
	 * in parallel. The MPN lookup catches products where TME tagged
	 * manufacturer_symbols, the symbol lookup catches products where TME has
	 * the catalogue entry but no MPN tag (BS-244DSM-R, etc. — `symbol`
	 * effectively IS the MPN for these). Doubling the requests is fine under
	 * TME's 50 rps cap.
	 */
	public function searchExactByMpnAsync(string $mpn): array
	{
		return [
			'exact_mpn' => $this->service->getProductsAsync(mpns: [$mpn]),
			'exact_sym' => $this->service->getProductsAsync(symbols: [$mpn])
		];
	}

	public function mapSearchExactByMpnResponses(array $responses, string $mpn): array
	{
		// /products endpoints return `data.elements` (NOT `data.products.elements`
		// — that's only on /products/search). The previous "data.products.elements"
		// parse silently yielded zero hits forever — TME never contributed to
		// completion-pass exact-match candidates.
		$seen = $out = [];
		foreach (['exact_mpn', 'exact_sym'] as $key) {
			$data = $this->safeArray($responses[$key] ?? null);
			foreach ($data['data']['elements'] ?? [] as $e) {
				$sym = (string)($e['symbol'] ?? '');
				if ($sym === '' || isset($seen[$sym])) {
					continue;
				}
				// Match by manufacturer_symbols[0] OR by symbol — covers
				// MPN-tagged entries AND symbol-only entries (the latter has
				// manufacturer_symbols: [])
				$rowMpn = (string)($e['manufacturer_symbols'][0] ?? '');
				if (strcasecmp($rowMpn, $mpn) !== 0 && strcasecmp($sym, $mpn) !== 0) {
					continue;
				}
				$seen[$sym] = true;
				$out[] = $this->mapLight($e, exactMpn: $mpn);
			}
		}
		return $out;
	}

	public function mapDetailsBatchResponses(array $responses, array $sourceSkus): array
	{
		if ($responses === []) {
			return [];
		}
		// Re-merge chunks into one big symbol-indexed map per endpoint
		$products = [];
		$params = [];
		$data = [];
		$files = [];
		foreach ($responses as $label => $response) {
			[$kind, $idx] = array_pad(explode('.', $label, 2), 2, '0');
			$arr = $this->safeArray($response);
			match ($kind) {
				'products' => $products += $this->indexBy($arr, 'symbol'),
				'parameters' => $params += $this->indexBy($arr, 'symbol'),
				'data' => $data += $this->indexBy($arr, 'symbol', basePath: 'data.elements'),
				'files' => $files += $this->indexBy($arr, 'symbol'),
				default => null
			};
		}
		$out = [];
		foreach ($sourceSkus as $sku) {
			$element = $products[$sku] ?? null;
			$out[$sku] = $element === null
				? null
				: $this->mapFull($element, $params[$sku] ?? null, $data[$sku] ?? null, $files[$sku] ?? null);
		}
		return $out;
	}

	private function safeArray(\Symfony\Contracts\HttpClient\ResponseInterface $response): ?array
	{
		try {
			return $response->toArray(false);
		} catch (\Throwable) {
			return null;
		}
	}

	private function mapLight(array $e, string $exactMpn = ''): InfoProviderSearchResult
	{
		// For symbol-only catalogue entries (manufacturer_symbols is empty,
		// e.g. BS-244DSM-R) the TME symbol IS effectively the MPN — it's how
		// the product is publicly addressable. Without this fallback the
		// merger drops such candidates entirely because manufacturerPartNumber
		// stays empty.
		$mpn = $e['manufacturer_symbols'][0] ?? ($e['symbol'] ?? '');
		return new InfoProviderSearchResult(
			source: 'tme',
			sourceSku: $e['symbol'] ?? '',
			manufacturerName: $e['manufacturer']['name'] ?? '',
			manufacturerPartNumber: $mpn,
			description: $e['description'] ?? null,
			imageUrl: $this->extractImageUrl($e),
			productUrl: $this->productUrl($e),
			packageName: null,
			categoryName: $e['category']['name'] ?? null,
			lifecycleStatus: $this->lifecycle($e),
			stock: null, // stock is only available via /products/data
			datasheetUrl: null, // datasheet is only available via /products/files
			isExactMatch: $exactMpn !== '' && strcasecmp($mpn, $exactMpn) === 0
		);
	}

	private function mapFull(array $e, ?array $paramElement, ?array $dataElement, ?array $filesElement = null): InfoProviderResult
	{
		// See mapLight() for the symbol-fallback rationale
		$mpn = $e['manufacturer_symbols'][0] ?? ($e['symbol'] ?? '');
		return new InfoProviderResult(
			source: 'tme',
			sourceSku: $e['symbol'] ?? '',
			manufacturerName: $e['manufacturer']['name'] ?? '',
			manufacturerPartNumber: $mpn,
			description: $e['description'] ?? null,
			imageUrl: $this->extractImageUrl($e),
			productUrl: $this->productUrl($e),
			packageName: $this->extractPackageName($paramElement),
			categoryName: $e['category']['name'] ?? null,
			lifecycleStatus: $this->lifecycle($e),
			stock: isset($dataElement['stock_quantity']) ? (int)$dataElement['stock_quantity'] : null,
			datasheetUrl: $this->extractDatasheetUrl($filesElement),
			currency: $dataElement['prices']['currency'] ?? null,
			parameters: $this->mapParameters($paramElement),
			priceBreaks: $this->mapPriceBreaks($dataElement['prices']['elements'] ?? []),
			rawSource: $e
		);
	}

	/**
	 * Mine the package label out of TME's /products/parameters response.
	 * Common label values: "Case", "Kind of package", "Type of package",
	 * "Housing". Returns the first matching parameter value or null.
	 */
	private function extractPackageName(?array $paramElement): ?string
	{
		$elements = $paramElement['parameters']['elements'] ?? [];
		$patterns = [
			'/\bcase\b/i',
			'/\b(?:kind|type)\s+of\s+package\b/i',
			'/\bpackage\b/i',
			'/\bhousing\b/i'
		];
		foreach ($patterns as $pattern) {
			foreach ($elements as $p) {
				$name = trim((string)($p['name'] ?? ''));
				if ($name === '' || preg_match($pattern, $name) !== 1) {
					continue;
				}
				$values = array_column($p['values'] ?? [], 'value');
				$value = trim((string)($values[0] ?? ''));
				if ($value !== '' && $value !== '-') {
					return $value;
				}
			}
		}
		return null;
	}

	private function lifecycle(array $e): ?ManufacturingStatus
	{
		$statuses = $e['product_status'] ?? [];
		if (!is_array($statuses) || $statuses === []) {
			return null;
		}
		// TME ships a list of tags ("ACTIVE", "ARCHIVED", "NEW", …). Pick
		// the first one that maps to a known canonical status; fall back
		// to Unknown if nothing matched (TME said *something* we can't
		// classify) so the caller can distinguish "TME silent" from
		// "TME said something we don't understand".
		foreach ($statuses as $s) {
			if (!is_string($s)) {
				continue;
			}
			if (isset(self::TME_STATUS_MAP[$s])) {
				return self::TME_STATUS_MAP[$s];
			}
			$mapped = ManufacturingStatus::fromRaw($s);
			if ($mapped !== null && $mapped !== ManufacturingStatus::Unknown) {
				return $mapped;
			}
		}
		return ManufacturingStatus::Unknown;
	}

	private function extractImageUrl(array $e): ?string
	{
		$img = $e['assets']['primary_photo']['prime']
			?? $e['assets']['primary_photo']['high_resolution']
			?? null;
		return $img !== null ? (str_starts_with($img, '//') ? 'https:' . $img : $img) : null;
	}

	/**
	 * Pick the datasheet from /products/files documents[]. TME tags each
	 * document with a `type` — "DTE" is the datasheet. Prefer EN language if
	 * multiple datasheets exist; fall back to the first DTE; fall back to the
	 * first document of any type if no DTE-tagged file is present.
	 */
	private function extractDatasheetUrl(?array $filesElement): ?string
	{
		$docs = $filesElement['documents']['elements'] ?? [];
		if (!is_array($docs) || $docs === []) {
			return null;
		}
		$pick = null;
		foreach ($docs as $d) {
			if (($d['type'] ?? null) !== 'DTE') continue;
			if (($d['language'] ?? null) === 'EN') {
				$pick = $d;
				break;
			}
			$pick ??= $d;
		}
		$pick ??= $docs[0];
		$url = $pick['url'] ?? null;
		if (!is_string($url) || $url === '') {
			return null;
		}
		return str_starts_with($url, '//') ? 'https:' . $url : $url;
	}

	private function productUrl(array $e): ?string
	{
		$sym = $e['symbol'] ?? null;
		return $sym !== null ? "https://www.tme.eu/en/details/$sym/" : null;
	}

	private function mapParameters(?array $paramElement): array
	{
		$elements = $paramElement['parameters']['elements'] ?? [];
		$out = [];
		foreach ($elements as $p) {
			$name = $p['name'] ?? '';
			$values = array_column($p['values'] ?? [], 'value');
			if ($name === '' || $values === []) {
				continue;
			}
			$out[] = new Parameter(rawName: $name, rawValue: implode(' | ', $values));
		}
		return $out;
	}

	private function mapPriceBreaks(array $tmePrices): array
	{
		$out = [];
		foreach ($tmePrices as $row) {
			$qty = $row['amount'] ?? null;
			$price = $row['price'] ?? null;
			if ($qty === null || $price === null) {
				continue;
			}
			$out[] = new PriceBreak(quantity: (int)$qty, price: (float)$price);
		}
		return $out;
	}

	/**
	 * Build a `symbol => element` map from a TME response like {data:{elements:[{symbol:..., ...}]}}
	 */
	private function indexBy(?array $response, string $key, string $basePath = 'data.elements'): array
	{
		if ($response === null) return [];
		$node = $response;
		foreach (explode('.', $basePath) as $part) {
			$node = $node[$part] ?? null;
			if (!is_array($node)) return [];
		}
		$out = [];
		foreach ($node as $row) {
			$id = $row[$key] ?? null;
			if ($id !== null) {
				$out[$id] = $row;
			}
		}
		return $out;
	}
}
