<?php

namespace Limas\Service\Integration\InfoProvider\Adapter;

use Limas\Service\Integration\InfoProvider\Contract\InfoProviderInterface;
use Limas\Service\Integration\InfoProvider\Contract\URLHandlerInterface;
use Limas\Service\Integration\InfoProvider\DigiKeyService;
use Limas\Service\Integration\InfoProvider\Dto\InfoProviderResult;
use Limas\Service\Integration\InfoProvider\Dto\InfoProviderSearchResult;
use Limas\Service\Integration\InfoProvider\Dto\Parameter;
use Limas\Service\Integration\InfoProvider\Dto\PriceBreak;
use Limas\Service\Integration\InfoProvider\Enum\ManufacturingStatus;
use Limas\Service\Integration\InfoProvider\ProviderCapability;


/**
 * Adapts DigiKey Product Information V4 keyword search response into normalised InfoProviderResult instances
 *
 * V4 product shape per survey (surveys/digikey/<mpn>.json → Products[]):
 *   - ManufacturerProductNumber, Manufacturer.{Id,Name}
 *   - Description.{ProductDescription,DetailedDescription}
 *   - DatasheetUrl, PhotoUrl, ProductUrl
 *   - QuantityAvailable, UnitPrice (currency depends on locale headers)
 *   - Parameters[] — array of {ParameterId, ParameterText, ValueId, ValueText, ParameterType, ...}
 *   - ProductVariations[] — package types per variation; first one carries DigiKey SKU + StandardPricing[] (price breaks)
 *   - Category.Name (top-level category)
 *   - ProductStatus.Status, Discontinued, EndOfLife, NormallyStocking — lifecycle signals
 */
final readonly class DigiKeyAdapter
	implements InfoProviderInterface, URLHandlerInterface
{
	public function __construct(
		private DigiKeyService $service,
		private string         $clientId = '',
		private string         $clientSecret = '',
		private string         $currency = 'EUR'
	)
	{
	}

	/** @return string[] */
	public function getHandledDomains(): array
	{
		// Both the public site and the sandbox use the same path shapes
		return ['digikey.com', 'sandbox.digikey.com'];
	}

	/**
	 * DigiKey product-detail URLs come in two stable shapes the regex handles in one pass:
	 *   /en/products/detail/<mfr>/<mpn>/<digikey-id>
	 *   /product-detail/en/<mfr>/<mpn>/<digikey-id>
	 *
	 * The trailing segment is DigiKey's own part number, which is also
	 * the value DigiKeyAdapter uses as `sourceSku` — return it so the
	 * aggregator can tightly auto-pick the matching candidate row.
	 *
	 * @return array{mpn: string, manufacturer: string, sourceSku?: string}|null
	 */
	public function tryExtractFromURL(string $url): ?array
	{
		$path = parse_url($url, PHP_URL_PATH);
		if (!is_string($path)) {
			return null;
		}
		if (preg_match('#/(?:en/products|product)-?/?detail(?:/en)?/([^/]+)/([^/]+)/([^/]+)#i', $path, $m) !== 1) {
			return null;
		}
		return [
			'manufacturer' => rawurldecode($m[1]),
			'mpn' => rawurldecode($m[2]),
			'sourceSku' => rawurldecode($m[3])
		];
	}

	public function getName(): string
	{
		return 'digikey';
	}

	public function isConfigured(): bool
	{
		return $this->clientId !== '' && $this->clientSecret !== '';
	}

	public function getCapabilities(): array
	{
		// V4 keyword search returns full Products[].{Manufacturer,Description,Datasheet,Photo,Parameters,
		// ProductVariations.{PackageType,StandardPricing}}. GTIN not requested in current query.
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

	public function searchByMpnAsync(string $mpn, int $limit = 10): array
	{
		return ['keyword' => $this->service->searchByKeywordAsync($mpn)];
	}

	public function mapSearchByMpnResponses(array $responses, string $mpn, int $limit = 10): array
	{
		$data = $this->service->awaitAndCache($responses['keyword'], keywordSearch: true);
		$products = array_slice($data['Products'] ?? [], 0, $limit);
		return array_map(fn(array $p) => $this->mapLight($p, exactMpn: $mpn), $products);
	}

	public function getDetails(string $sourceSku): ?InfoProviderResult
	{
		return $this->mapDetailsResponses($this->getDetailsAsync($sourceSku), $sourceSku);
	}

	public function getDetailsAsync(string $sourceSku): array
	{
		// V4 productdetails expects manufacturer part number, not DigiKey SKU.
		// Our light SearchResult uses DigiKeyProductNumber as sourceSku, so we
		// fall back to keyword search and pick the matching variation.
		return ['keyword' => $this->service->searchByKeywordAsync($sourceSku)];
	}

	public function mapDetailsResponses(array $responses, string $sourceSku): ?InfoProviderResult
	{
		$data = $this->service->awaitAndCache($responses['keyword'], keywordSearch: true);
		foreach ($data['Products'] ?? [] as $p) {
			foreach ($p['ProductVariations'] ?? [] as $v) {
				if (($v['DigiKeyProductNumber'] ?? null) === $sourceSku) {
					return $this->mapFull($p, $v);
				}
			}
		}
		return null;
	}

	public function getDetailsBatchAsync(array $sourceSkus): array
	{
		// DigiKey V4 has no list-of-skus endpoint; fan out per-sku and let curl_multi pipeline them
		$out = [];
		foreach ($sourceSkus as $sku) {
			$out['kw.' . $sku] = $this->service->searchByKeywordAsync($sku);
		}
		return $out;
	}

	public function searchExactByMpnAsync(string $mpn): array
	{
		// V4 has no strict-MPN endpoint that doesn't require a manufacturerId
		// integer (which we don't have on hand at completion time). Keyword
		// search returns relevance-sorted matches that almost always include
		// the exact MPN inside the top results; we post-filter on the
		// `ManufacturerProductNumber` field. Cost: one extra HTTP per missing
		// candidate, cached.
		return ['exact' => $this->service->searchByKeywordAsync($mpn)];
	}

	public function mapSearchExactByMpnResponses(array $responses, string $mpn): array
	{
		if (!isset($responses['exact'])) {
			return [];
		}
		$data = $this->service->awaitAndCache($responses['exact'], keywordSearch: true);
		$out = [];
		foreach ($data['Products'] ?? [] as $p) {
			$rowMpn = $p['ManufacturerProductNumber'] ?? '';
			if (strcasecmp((string)$rowMpn, $mpn) !== 0) {
				continue;
			}
			$out[] = $this->mapLight($p, exactMpn: $mpn);
		}
		return $out;
	}

	public function mapDetailsBatchResponses(array $responses, array $sourceSkus): array
	{
		$out = [];
		foreach ($sourceSkus as $sku) {
			$key = 'kw.' . $sku;
			$out[$sku] = null;
			if (!isset($responses[$key])) {
				continue;
			}
			$data = $this->service->awaitAndCache($responses[$key], keywordSearch: true);
			foreach ($data['Products'] ?? [] as $p) {
				foreach ($p['ProductVariations'] ?? [] as $v) {
					if (($v['DigiKeyProductNumber'] ?? null) === $sku) {
						$out[$sku] = $this->mapFull($p, $v);
						break 2;
					}
				}
			}
		}
		return $out;
	}

	/**
	 * Light mapping — basic descriptors + package/lifecycle/stock, no parameters/pricing
	 */
	private function mapLight(array $p, string $exactMpn = ''): InfoProviderSearchResult
	{
		$variation = $p['ProductVariations'][0] ?? [];
		$mpn = $p['ManufacturerProductNumber'] ?? '';
		return new InfoProviderSearchResult(
			source: 'digikey',
			sourceSku: $variation['DigiKeyProductNumber'] ?? $mpn,
			manufacturerName: $p['Manufacturer']['Name'] ?? '',
			manufacturerPartNumber: $mpn,
			description: $p['Description']['DetailedDescription'] ?? ($p['Description']['ProductDescription'] ?? null),
			imageUrl: $p['PhotoUrl'] ?? null,
			productUrl: $p['ProductUrl'] ?? null,
			packageName: $this->extractPackageName($p, $variation),
			categoryName: $p['Category']['Name'] ?? null,
			lifecycleStatus: $this->extractLifecycle($p),
			stock: isset($p['QuantityAvailable']) ? (int)$p['QuantityAvailable'] : null,
			datasheetUrl: $p['DatasheetUrl'] ?? null,
			isExactMatch: $exactMpn !== '' && strcasecmp($mpn, $exactMpn) === 0
		);
	}

	/**
	 * Full detail — parameters, price breaks, currency, raw payload
	 */
	private function mapFull(array $p, ?array $variation = null): InfoProviderResult
	{
		$variation ??= $p['ProductVariations'][0] ?? [];

		return new InfoProviderResult(
			source: 'digikey',
			sourceSku: $variation['DigiKeyProductNumber'] ?? ($p['ManufacturerProductNumber'] ?? ''),
			manufacturerName: $p['Manufacturer']['Name'] ?? '',
			manufacturerPartNumber: $p['ManufacturerProductNumber'] ?? '',
			description: $p['Description']['DetailedDescription'] ?? ($p['Description']['ProductDescription'] ?? null),
			imageUrl: $p['PhotoUrl'] ?? null,
			productUrl: $p['ProductUrl'] ?? null,
			packageName: $this->extractPackageName($p, $variation),
			categoryName: $p['Category']['Name'] ?? null,
			lifecycleStatus: $this->extractLifecycle($p),
			stock: isset($p['QuantityAvailable']) ? (int)$p['QuantityAvailable'] : null,
			datasheetUrl: $p['DatasheetUrl'] ?? null,
			currency: $this->currency,
			parameters: $this->mapParameters($p['Parameters'] ?? []),
			priceBreaks: $this->mapPriceBreaks($variation['StandardPricing'] ?? []),
			rawSource: $p
		);
	}

	private function extractLifecycle(array $p): ?ManufacturingStatus
	{
		// Explicit DigiKey flags trump the textual status — when DigiKey
		// flips `EndOfLife = true` the textual `ProductStatus.Status` is
		// often still "Active". Map the flags first.
		if (($p['Discontinued'] ?? false) === true) {
			return ManufacturingStatus::Discontinued;
		}
		if (($p['EndOfLife'] ?? false) === true) {
			return ManufacturingStatus::EndOfLife;
		}
		return ManufacturingStatus::fromRaw($p['ProductStatus']['Status'] ?? null);
	}

	/**
	 * DigiKey's `ProductVariations[*].PackageType.Name` is the SHIPPING format
	 * ("Cut Tape (CT)", "Tape & Reel (TR)", "Tube", "Bulk", …) — not the
	 * electrical package (TO-92, SOIC-8). The actual package is in the
	 * Parameters[] payload under "Package / Case" or "Supplier Device Package".
	 *
	 * Strategy:
	 *   1. Prefer "Package / Case" from Parameters
	 *   2. Fall back to "Supplier Device Package"
	 *   3. As a last resort, use PackageType.Name ONLY when it doesn't look
	 *      like a shipping/packaging format — covers parts where DigiKey
	 *      categorises the actual package under PackageType.
	 */
	private function extractPackageName(array $p, array $variation): ?string
	{
		$shippingPatterns = '/\b(cut tape|tape\s*&\s*reel|t\s*&\s*r|tube|bulk|reel|strip|tray|ammo)\b/i';
		foreach (($p['Parameters'] ?? []) as $param) {
			$name = $param['ParameterText'] ?? ($param['Parameter'] ?? '');
			$value = $param['ValueText'] ?? ($param['Value'] ?? '');
			if ($value === '' || $value === '-') {
				continue;
			}
			if (in_array($name, ['Package / Case', 'Supplier Device Package'], true)) {
				return (string)$value;
			}
		}
		$pkgType = $variation['PackageType']['Name'] ?? null;
		if ($pkgType !== null && !preg_match($shippingPatterns, $pkgType)) {
			return (string)$pkgType;
		}
		return null;
	}

	/**
	 * DigiKey Parameters[] entries: {ParameterId, ParameterText, ValueId, ValueText, ParameterType}
	 */
	private function mapParameters(array $params): array
	{
		$out = [];
		foreach ($params as $p) {
			$name = $p['ParameterText'] ?? ($p['Parameter'] ?? '');
			$value = $p['ValueText'] ?? ($p['Value'] ?? '');
			if ($name === '' || $value === '' || $value === '-') {
				continue;
			}
			$out[] = new Parameter(rawName: (string)$name, rawValue: (string)$value);
		}
		return $out;
	}

	/**
	 * DigiKey StandardPricing[] entries: {BreakQuantity, UnitPrice, TotalPrice} per ProductVariations[*].StandardPricing
	 */
	private function mapPriceBreaks(array $breaks): array
	{
		$out = [];
		foreach ($breaks as $b) {
			$qty = $b['BreakQuantity'] ?? null;
			$price = $b['UnitPrice'] ?? null;
			if ($qty === null || $price === null) {
				continue;
			}
			$out[] = new PriceBreak(quantity: (int)$qty, price: (float)$price);
		}
		return $out;
	}
}
