<?php

namespace Limas\Service\Integration\InfoProvider\Adapter;

use Limas\Service\Integration\InfoProvider\Contract\InfoProviderInterface;
use Limas\Service\Integration\InfoProvider\Contract\URLHandlerInterface;
use Limas\Service\Integration\InfoProvider\Dto\InfoProviderResult;
use Limas\Service\Integration\InfoProvider\Dto\InfoProviderSearchResult;
use Limas\Service\Integration\InfoProvider\Dto\Parameter;
use Limas\Service\Integration\InfoProvider\Dto\PriceBreak;
use Limas\Service\Integration\InfoProvider\Enum\ManufacturingStatus;
use Limas\Service\Integration\InfoProvider\FarnellService;
use Limas\Service\Integration\InfoProvider\ProviderCapability;


/**
 * Adapts Farnell/element14 keyword-search response into InfoProviderResult
 *
 * Product shape per survey (surveys/farnell/<mpn>.json → keywordSearchReturn.products[]):
 *   - sku, displayName, productStatus, comingSoon
 *   - rohsStatusCode, packSize, unitOfMeasure
 *   - image.{baseName,vrntPath} — needs to be concatenated with a CDN prefix
 *   - datasheets[] — array of {type, description, url}
 *   - prices[] — array of {from, to, cost}  (Farnell ranges, not single quantities)
 *   - inv (stock count)
 *   - vendorId, vendorName, brandName, brandId
 *   - translatedManufacturerPartNumber, translatedMinimumOrderQuality
 *   - attributes[] — {attributeLabel, attributeUnit, attributeValue}
 */
final class FarnellAdapter
	implements InfoProviderInterface, URLHandlerInterface
{
	/**
	 * The element14 API serves multiple sister catalogs through the same
	 * endpoint distinguished by `storeInfo.id` (sk.farnell.com, uk.farnell.com,
	 * www.newark.com, …). One API key spans them all, so we expose the same
	 * adapter class under different `getName()` values — DI wires two service
	 * instances (Farnell EU + Newark US), each with its own cache pool and
	 * adapter wrapper. The aggregator then treats them as independent sources
	 * so the user can compare EU vs US pricing for the same MPN.
	 */
	public function __construct(
		private readonly FarnellService $service,
		private readonly string         $apiKey = '',
		private readonly string         $currency = 'EUR',
		private readonly string         $name = 'farnell'
	)
	{
	}

	public function getName(): string
	{
		return $this->name;
	}

	/** @return string[] */
	public function getHandledDomains(): array
	{
		// Premier Farnell family: regional Farnell + Newark + element14
		// share the same URL shape, only the host differs (sk.farnell.com,
		// uk.farnell.com, www.newark.com, au.element14.com, sg.element14.com, …).
		// All three adapter instances (Farnell EU, Newark US, element14 APAC)
		// advertise the same domain list since the URL convention is identical;
		// the first match in the aggregator's URL-resolve walk wins.
		return ['farnell.com', 'newark.com', 'element14.com'];
	}

	/**
	 * Farnell-family product-detail URLs share the
	 *   /<mfr>/<mpn>/<description-slug>/dp/<sku>
	 * shape across every regional storefront. The trailing /dp/<sku>
	 * number is Farnell's own SKU (what FarnellAdapter uses as
	 * `sourceSku`) — return it so the aggregator can tightly auto-pick
	 * the matching candidate row even when MPN is ambiguous across
	 * package variants.
	 *
	 * @return array{mpn: string, manufacturer: string, sourceSku?: string}|null
	 */
	public function tryExtractFromURL(string $url): ?array
	{
		$path = parse_url($url, PHP_URL_PATH);
		if (!is_string($path)) {
			return null;
		}
		if (preg_match('#^/([^/]+)/([^/]+)/[^/]+/dp/(\d+)#i', $path, $m) !== 1) {
			return null;
		}
		return [
			'manufacturer' => rawurldecode($m[1]),
			'mpn' => rawurldecode($m[2]),
			'sourceSku' => $m[3]
		];
	}

	public function isConfigured(): bool
	{
		return $this->apiKey !== '';
	}

	public function getCapabilities(): array
	{
		// Search response includes attributes[], prices[], datasheets[], image.
		// FOOTPRINT is mined out of attributes[] via extractPackageName() —
		// labels like "Case / Package", "Transistor Case Style", etc.
		// GTIN not in the default search payload for element14 catalog.
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
		$products = $this->extractProducts($this->service->awaitAndCache($responses['keyword']));
		return array_map(fn(array $p) => $this->mapLight($p, exactMpn: $mpn), array_slice($products, 0, $limit));
	}

	public function getDetails(string $sourceSku): ?InfoProviderResult
	{
		return $this->mapDetailsResponses($this->getDetailsAsync($sourceSku), $sourceSku);
	}

	public function getDetailsAsync(string $sourceSku): array
	{
		// Farnell sku is its own SKU, not the MPN — isManufacturerPart=false.
		return ['detail' => $this->service->searchByPartNumberAsync($sourceSku, false)];
	}

	public function mapDetailsResponses(array $responses, string $sourceSku): ?InfoProviderResult
	{
		$products = $this->extractProducts($this->service->awaitAndCache($responses['detail']));
		foreach ($products as $p) {
			if ((string)($p['sku'] ?? '') === $sourceSku) {
				return $this->mapFull($p);
			}
		}
		return null;
	}

	public function getDetailsBatchAsync(array $sourceSkus): array
	{
		// Farnell has no batched detail endpoint — fan out per-sku. curl_multi
		// still pipelines them, just no server-side batching.
		$out = [];
		foreach ($sourceSkus as $sku) {
			$out['detail.' . $sku] = $this->service->searchByPartNumberAsync($sku, false);
		}
		return $out;
	}

	public function mapDetailsBatchResponses(array $responses, array $sourceSkus): array
	{
		$out = [];
		foreach ($sourceSkus as $sku) {
			// Numeric-only Farnell SKUs (e.g. "3009700") get coerced to int
			// when they pass through any associative-array key, so $sku may
			// arrive here as int despite the declared array<string> type.
			// Cast to string so the strict === below doesn't false-negative
			// on string-vs-int.
			/** @phpstan-ignore cast.useless */
			$sku = (string)$sku;
			$key = 'detail.' . $sku;
			if (!isset($responses[$key])) {
				$out[$sku] = null;
				continue;
			}
			$data = $this->service->awaitAndCache($responses[$key]);
			$out[$sku] = null;
			foreach ($this->extractProducts($data) as $p) {
				if ((string)($p['sku'] ?? '') === $sku) {
					$out[$sku] = $this->mapFull($p);
					break;
				}
			}
		}
		return $out;
	}

	public function searchExactByMpnAsync(string $mpn): array
	{
		// Strict `manuPartNum:` term — Farnell only returns rows whose
		// manufacturer part number equals $mpn (no fuzzy keyword expansion)
		return ['exact' => $this->service->searchByPartNumberAsync($mpn, isManufacturerPart: true)];
	}

	public function mapSearchExactByMpnResponses(array $responses, string $mpn): array
	{
		if (!isset($responses['exact'])) {
			return [];
		}
		$data = $this->service->awaitAndCache($responses['exact']);
		$out = [];
		foreach ($this->extractProducts($data) as $p) {
			$rowMpn = $p['translatedManufacturerPartNumber'] ?? ($p['manufacturerPartNumber'] ?? '');
			// Defensive case-insensitive check — `manuPartNum:` is strict at
			// the API level, but normalising here keeps the contract uniform
			// across adapters (DigiKey's keyword path actually needs it)
			if (strcasecmp((string)$rowMpn, $mpn) !== 0) {
				continue;
			}
			$out[] = $this->mapLight($p, exactMpn: $mpn);
		}
		return $out;
	}

	/**
	 * Farnell wraps results under one of three top-level keys depending on
	 * which search variant the underlying service called
	 */
	private function extractProducts(array $response): array
	{
		return $response['keywordSearchReturn']['products']
			?? $response['premierFarnellPartNumberReturn']['products']
			?? $response['manufacturerPartNumberSearchReturn']['products']
			?? [];
	}

	private function mapLight(array $p, string $exactMpn = ''): InfoProviderSearchResult
	{
		$mpn = $p['translatedManufacturerPartNumber'] ?? ($p['manufacturerPartNumber'] ?? '');
		$mfr = $p['vendorName'] ?? ($p['brandName'] ?? '');
		return new InfoProviderSearchResult(
			source: $this->name,
			sourceSku: (string)($p['sku'] ?? ''),
			manufacturerName: $mfr,
			manufacturerPartNumber: $mpn,
			description: $this->cleanDescription($p['displayName'] ?? null, $mfr, $mpn),
			imageUrl: $this->buildImageUrl($p['image'] ?? null),
			productUrl: isset($p['sku']) ? "https://{$this->storeHost()}/w/c/{$p['sku']}" : null,
			packageName: $this->extractPackageName($p['attributes'] ?? []),
			categoryName: null,
			lifecycleStatus: $this->lifecycle($p),
			stock: isset($p['inv']) ? (int)$p['inv'] : null,
			datasheetUrl: $p['datasheets'][0]['url'] ?? null,
			isExactMatch: $exactMpn !== '' && strcasecmp($mpn, $exactMpn) === 0
		);
	}

	private function mapFull(array $p): InfoProviderResult
	{
		$mpn = $p['translatedManufacturerPartNumber'] ?? ($p['manufacturerPartNumber'] ?? '');
		$mfr = $p['vendorName'] ?? ($p['brandName'] ?? '');
		return new InfoProviderResult(
			source: $this->name,
			sourceSku: (string)($p['sku'] ?? ''),
			manufacturerName: $mfr,
			manufacturerPartNumber: $mpn,
			description: $this->cleanDescription($p['displayName'] ?? null, $mfr, $mpn),
			imageUrl: $this->buildImageUrl($p['image'] ?? null),
			productUrl: isset($p['sku']) ? "https://{$this->storeHost()}/w/c/{$p['sku']}" : null,
			packageName: $this->extractPackageName($p['attributes'] ?? []),
			categoryName: null,
			lifecycleStatus: $this->lifecycle($p),
			stock: isset($p['inv']) ? (int)$p['inv'] : null,
			datasheetUrl: $p['datasheets'][0]['url'] ?? null,
			currency: $this->currency,
			parameters: $this->mapParameters($p['attributes'] ?? []),
			priceBreaks: $this->mapPriceBreaks($p['prices'] ?? []),
			rawSource: $p
		);
	}

	/**
	 * Mine the package/footprint label out of Farnell's flat attributes[] array.
	 * Labels seen in the wild: "Case / Package", "Case Style", "IC Case",
	 * "Transistor Case Style", "Mounting Style" (sometimes carries SMD/THT
	 * but not a real footprint — checked AFTER the more specific patterns).
	 */
	private function extractPackageName(array $attributes): ?string
	{
		// Priority-ordered label patterns; first hit with a non-empty value wins.
		$patterns = [
			'/\bcase\s*\/\s*package\b/i',
			'/\bic\s+case\b/i',
			'/\bcase\s+style\b/i',
			'/\btransistor\s+case\s+style\b/i',
			'/\b(?:resistor|capacitor|inductor)\s+case(?:\s*\/\s*package)?\b/i',
			'/\bpackage\s+type\b/i',
			'/\bpackage\s*\/\s*case\b/i',
		];
		foreach ($patterns as $pattern) {
			foreach ($attributes as $a) {
				$label = trim((string)($a['attributeLabel'] ?? ''));
				$value = trim((string)($a['attributeValue'] ?? ''));
				if ($label === '' || $value === '' || $value === '-') {
					continue;
				}
				if (preg_match($pattern, $label) === 1) {
					return $value;
				}
			}
		}
		return null;
	}

	/**
	 * Farnell uses a single `displayName` field that concatenates manufacturer +
	 * MPN + the actual description, e.g.:
	 *
	 *   "STMICROELECTRONICS - ULN2003A - Darlington Transistor, Darlington, NPN, …"
	 *
	 * We already carry manufacturer + MPN as separate first-class fields, so the
	 * prefix is pure noise. Strip "{MFR} - {MPN} - " (case-insensitive) when
	 * present; fall back to the raw value otherwise.
	 */
	private function cleanDescription(?string $raw, string $mfr, string $mpn): ?string
	{
		if ($raw === null || $raw === '') {
			return $raw;
		}
		$candidates = [];
		if ($mfr !== '' && $mpn !== '') {
			$candidates[] = "$mfr - $mpn - ";
		}
		if ($mpn !== '') {
			$candidates[] = "$mpn - ";
		}
		if ($mfr !== '') {
			$candidates[] = "$mfr - ";
		}
		foreach ($candidates as $prefix) {
			if (mb_stripos($raw, $prefix) === 0) {
				return trim(mb_substr($raw, mb_strlen($prefix)));
			}
		}
		return $raw;
	}

	private function lifecycle(array $p): ?ManufacturingStatus
	{
		// `comingSoon=true` overrides whatever textual status Farnell put
		// in — the part literally isn't shipping yet, so PreRelease wins
		if (($p['comingSoon'] ?? false) === true) {
			return ManufacturingStatus::PreRelease;
		}
		// Farnell's `productStatus` mixes manufacturing lifecycle with
		// inventory state: STOCKED / DIRECT_SHIP / NO_LONGER_STOCKED are
		// purely about whether Farnell holds it, not whether the part is
		// still manufactured. Only NO_LONGER_MANUFACTURED communicates a
		// real lifecycle signal — map everything else to null.
		$status = $p['productStatus'] ?? null;
		if (!is_string($status) || $status === '') {
			return null;
		}
		return match (strtoupper($status)) {
			'NO_LONGER_MANUFACTURED' => ManufacturingStatus::Discontinued,
			'STOCKED', 'DIRECT_SHIP', 'NO_LONGER_STOCKED', 'AVAILABLE', 'BACKORDERED' => null,
			default => ManufacturingStatus::fromRaw($status)
		};
	}

	/**
	 * Per the element14 Product Search API docs, image URL template is:
	 *
	 *   https://{storeInfo.id}/productimages/standard/{locale}/{baseName}
	 *
	 * `vrntPath` is NOT a path segment — it tells you which locale to use:
	 *   - "nio/"     → en_US (Newark / north-american catalog)
	 *   - "farnell/" → en_GB (default for sk/uk/de/etc. catalogs)
	 *
	 * `baseName` already starts with a slash. The host is whatever we
	 * configured for the API request (sk.farnell.com by default).
	 *
	 * Examples from the docs:
	 *   https://uk.farnell.com/productimages/standard/en_GB/GE20TSSOP-40.jpg
	 *   https://fr.farnell.com/productimages/standard/fr_FR/GE20TSSOP-40.jpg
	 */
	private function buildImageUrl(?array $image): ?string
	{
		if (!is_array($image)) {
			return null;
		}
		$base = $image['baseName'] ?? '';
		if (!is_string($base) || $base === '') {
			return null;
		}
		$vrnt = is_string($image['vrntPath'] ?? null) ? trim($image['vrntPath'], '/') : '';
		$locale = $vrnt === 'nio' ? 'en_US' : 'en_GB';
		return sprintf(
			'https://%s/productimages/standard/%s/%s',
			$this->storeHost(),
			$locale,
			ltrim($base, '/')
		);
	}

	private function storeHost(): string
	{
		$store = $this->service->getStoreId();
		return $store !== '' ? $store : 'sk.farnell.com';
	}

	/**
	 * Farnell's `attributes[]` mixes real electronics specs with catalog
	 * metadata (customs tariff codes, REACH/RoHS compliance flags, export
	 * controls, internal canonical flags). These would otherwise pollute the
	 * ParameterAlias table as auto-discovered unverified rows and clutter
	 * every Part's parameter grid. Drop them at the adapter boundary.
	 */
	private const array FARNELL_METADATA_SKIP = [
		'tariffCode',
		'SVHC',
		'productTraceability',
		'euEccn',
		'usEccn',
		'rohsCompliant',
		'rohsPhthalatesCompliant',
		'hazardous',
		'isCanonical'
	];

	private function mapParameters(array $attributes): array
	{
		$out = [];
		foreach ($attributes as $a) {
			$name = $a['attributeLabel'] ?? '';
			$unit = $a['attributeUnit'] ?? null;
			$value = $a['attributeValue'] ?? '';
			if ($name === '' || $value === '' || $value === '-') {
				continue;
			}
			if (in_array($name, self::FARNELL_METADATA_SKIP, true)) {
				continue;
			}
			$out[] = new Parameter(
				rawName: $name,
				rawValue: $unit ? "$value $unit" : (string)$value,
				rawUnit: $unit !== '' ? $unit : null
			);
		}
		return $out;
	}

	/**
	 * Farnell prices[] uses ranges {from, to, cost}. We turn each range into a
	 * PriceBreak with `quantity = from`
	 */
	private function mapPriceBreaks(array $prices): array
	{
		$out = [];
		foreach ($prices as $row) {
			$from = $row['from'] ?? null;
			$cost = $row['cost'] ?? null;
			if ($from === null || $cost === null) {
				continue;
			}
			$out[] = new PriceBreak(quantity: (int)$from, price: (float)$cost);
		}
		usort($out, fn(PriceBreak $a, PriceBreak $b) => $a->quantity <=> $b->quantity);
		return $out;
	}
}
