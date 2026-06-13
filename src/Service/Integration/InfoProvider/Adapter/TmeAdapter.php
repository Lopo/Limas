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
		// FOOTPRINT lives inside parameters (e.g. "Case - inch"), no top-level field.
		// GTIN: TME returns `ean` on the product but it's often empty for components.
		return [
			ProviderCapability::BASIC,
			ProviderCapability::PICTURE,
			ProviderCapability::DATASHEET,
			ProviderCapability::PRICE,
			ProviderCapability::PARAMETERS
		];
	}

	public function searchByMpn(string $mpn, int $limit = 10): array
	{
		return $this->mapSearchByMpnResponses($this->searchByMpnAsync($mpn, $limit), $mpn, $limit);
	}

	public function searchByMpnAsync(string $mpn, int $limit = 10): array
	{
		return ['search' => $this->service->searchByKeywordAsync($mpn)];
	}

	public function mapSearchByMpnResponses(array $responses, string $mpn, int $limit = 10): array
	{
		$data = $responses['search']->toArray(false);
		$elements = array_slice($data['data']['products']['elements'] ?? [], 0, $limit);
		return array_map(fn(array $e) => $this->mapLight($e, exactMpn: $mpn), $elements);
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

	public function searchExactByMpnAsync(string $mpn): array
	{
		// TME's /products endpoint accepts `mpns[]=<MPN>` for strict lookup —
		// returns full product objects (manufacturer, category, assets) but
		// NOT parameters/prices (those still need /products/parameters and
		// /products/data). For completion-pass purposes the light DTO is
		// enough; the aggregator will fire batched detail fetch on the
		// returned SKUs after filtering by canonical mfr.
		return ['exact' => $this->service->getProductsAsync(mpns: [$mpn])];
	}

	public function mapSearchExactByMpnResponses(array $responses, string $mpn): array
	{
		if (!isset($responses['exact'])) {
			return [];
		}
		$data = $this->safeArray($responses['exact']);
		$elements = $data['data']['products']['elements'] ?? [];
		$out = [];
		foreach ($elements as $e) {
			$rowMpn = $e['manufacturer_symbols'][0] ?? '';
			// Defensive: TME's mpns[] match is exact but a single MPN may map
			// to multiple TME symbols (different packaging/quantities). Keep
			// all of them — aggregator picks by canonical mfr.
			if (strcasecmp((string)$rowMpn, $mpn) !== 0) {
				continue;
			}
			$out[] = $this->mapLight($e, exactMpn: $mpn);
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
		$mpn = $e['manufacturer_symbols'][0] ?? '';
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
		$mpn = $e['manufacturer_symbols'][0] ?? '';
		return new InfoProviderResult(
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
			stock: isset($dataElement['stock_quantity']) ? (int)$dataElement['stock_quantity'] : null,
			datasheetUrl: $this->extractDatasheetUrl($filesElement),
			currency: $dataElement['prices']['currency'] ?? null,
			parameters: $this->mapParameters($paramElement),
			priceBreaks: $this->mapPriceBreaks($dataElement['prices']['elements'] ?? []),
			rawSource: $e
		);
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
