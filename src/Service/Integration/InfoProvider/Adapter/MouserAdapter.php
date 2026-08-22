<?php

namespace Limas\Service\Integration\InfoProvider\Adapter;

use Limas\Service\Integration\InfoProvider\Contract\InfoProviderInterface;
use Limas\Service\Integration\InfoProvider\Dto\InfoProviderResult;
use Limas\Service\Integration\InfoProvider\Dto\InfoProviderSearchResult;
use Limas\Service\Integration\InfoProvider\Dto\Parameter;
use Limas\Service\Integration\InfoProvider\Dto\PriceBreak;
use Limas\Service\Integration\InfoProvider\Enum\ManufacturingStatus;
use Limas\Service\Integration\InfoProvider\MouserService;
use Limas\Service\Integration\InfoProvider\ProviderCapability;


/**
 * Adapts Mouser Search API V2 responses into InfoProviderResult
 *
 * Single-phase: Mouser's keyword/partnumber search already returns the full
 * MouserPart payload (description, datasheet, image, pricing, attributes), so
 * Phase-2 detail is just a re-fetch by MouserPartNumber, mapped the richer way
 *
 * MouserPart field map (live shape 2026-08-20):
 *   - ManufacturerPartNumber / Manufacturer / Description
 *   - MouserPartNumber (our sourceSku, e.g. "737-SMC-1-40-1-GT")
 *   - DataSheetUrl / ImagePath / ProductDetailUrl / Category
 *   - AvailabilityInStock ("67") / Availability ("67 In Stock")
 *   - LifecycleStatus ("Active" / "Factory Special Order" / …)
 *   - ProductAttributes[] {AttributeName, AttributeValue}
 *   - PriceBreaks[] {Quantity:int, Price:"2,79 €" (localized string!), Currency}
 *
 * URL handling lives in the standalone MouserUrlHandler, so this adapter
 * implements InfoProviderInterface only
 */
final class MouserAdapter
	implements InfoProviderInterface
{
	/**
	 * Mouser attribute names that are commercial packaging metadata, not
	 * technical specs — dropped so they don't pollute the ParameterAlias table
	 * and every Part's parameter grid. Package/Case is intentionally NOT here:
	 * it's mined into packageName AND kept as a parameter.
	 */
	private const array COMMERCIAL_ATTR_SKIP = [
		'Packaging',
		'Standard Pack Qty',
		'Factory Pack Quantity',
		'Reeling'
	];


	public function __construct(
		private readonly MouserService $service,
		private readonly string        $currency = 'EUR',
		private readonly string        $name = 'mouser'
	)
	{
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function getDisplayName(): string
	{
		return 'Mouser';
	}

	public function getAttribution(): string
	{
		// Mouser API Terms of Use (mouser.com/en/apiterms) require you to
		// "clearly and conspicuously attribute the source of all Mouser
		// Electronics Data" — no fixed wording is mandated (same posture as
		// DigiKey), so this is our chosen clear/conspicuous credit
		return 'Data provided by Mouser Electronics';
	}

	public function isConfigured(): bool
	{
		return $this->service->isConfigured();
	}

	public function getCapabilities(): array
	{
		// Mouser's Search API returns only basic data + price/stock + (usually)
		// a datasheet + image. Its ProductAttributes[] are commercial packaging
		// metadata (Packaging, Standard Pack Qty) — NOT technical specs — and
		// there is no product-detail endpoint that returns real parameters. So
		// no PARAMETERS/FOOTPRINT capability: the aggregator fills those from
		// other sources (DigiKey/Farnell/LCSC) and merges Mouser's price/stock in.
		return [
			ProviderCapability::BASIC,
			ProviderCapability::PICTURE,
			ProviderCapability::DATASHEET,
			ProviderCapability::PRICE
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
		$parts = $this->extractParts($this->service->awaitAndCache($responses['keyword']));
		return array_map(fn(array $p) => $this->mapLight($p, exactMpn: $mpn), array_slice($parts, 0, $limit));
	}

	public function getDetails(string $sourceSku): ?InfoProviderResult
	{
		return $this->mapDetailsResponses($this->getDetailsAsync($sourceSku), $sourceSku);
	}

	public function getDetailsAsync(string $sourceSku): array
	{
		// sourceSku is the MouserPartNumber; an exact part-number lookup returns it
		return ['detail' => $this->service->searchByPartnumberAsync($sourceSku, 'Exact')];
	}

	public function mapDetailsResponses(array $responses, string $sourceSku): ?InfoProviderResult
	{
		foreach ($this->extractParts($this->service->awaitAndCache($responses['detail'])) as $p) {
			if ((string)($p['MouserPartNumber'] ?? '') === $sourceSku) {
				return $this->mapFull($p);
			}
		}
		return null;
	}

	public function getDetailsBatchAsync(array $sourceSkus): array
	{
		// Mouser's partnumber endpoint accepts pipe-separated PNs, but the
		// per-sku fan-out keeps mapping unambiguous; curl_multi still pipelines
		$out = [];
		foreach ($sourceSkus as $sku) {
			$out['detail.' . $sku] = $this->service->searchByPartnumberAsync($sku, 'Exact');
		}
		return $out;
	}

	public function mapDetailsBatchResponses(array $responses, array $sourceSkus): array
	{
		$out = [];
		foreach ($sourceSkus as $sku) {
			$key = 'detail.' . $sku;
			$out[$sku] = null;
			if (!isset($responses[$key])) {
				continue;
			}
			foreach ($this->extractParts($this->service->awaitAndCache($responses[$key])) as $p) {
				if ((string)($p['MouserPartNumber'] ?? '') === $sku) {
					$out[$sku] = $this->mapFull($p);
					break;
				}
			}
		}
		return $out;
	}

	public function searchExactByMpnAsync(string $mpn): array
	{
		// Exact part-number search on the MPN — Mouser matches manufacturer PNs
		return ['exact' => $this->service->searchByPartnumberAsync($mpn, 'Exact')];
	}

	public function mapSearchExactByMpnResponses(array $responses, string $mpn): array
	{
		if (!isset($responses['exact'])) {
			return [];
		}
		$out = [];
		foreach ($this->extractParts($this->service->awaitAndCache($responses['exact'])) as $p) {
			// 'Exact' is strict at the API level, but normalise defensively so
			// the contract matches the other adapters (case-insensitive MPN)
			if (strcasecmp((string)($p['ManufacturerPartNumber'] ?? ''), $mpn) !== 0) {
				continue;
			}
			$out[] = $this->mapLight($p, exactMpn: $mpn);
		}
		return $out;
	}

	private function extractParts(array $response): array
	{
		return $response['SearchResults']['Parts'] ?? [];
	}

	private function mapLight(array $p, string $exactMpn = ''): InfoProviderSearchResult
	{
		$mpn = (string)($p['ManufacturerPartNumber'] ?? '');
		return new InfoProviderSearchResult(
			source: $this->name,
			sourceSku: (string)($p['MouserPartNumber'] ?? ''),
			manufacturerName: (string)($p['Manufacturer'] ?? ''),
			manufacturerPartNumber: $mpn,
			description: $this->trimOrNull($p['Description'] ?? null),
			imageUrl: $this->trimOrNull($p['ImagePath'] ?? null),
			productUrl: $this->trimOrNull($p['ProductDetailUrl'] ?? null),
			packageName: $this->extractPackageName($p['ProductAttributes'] ?? []),
			categoryName: $this->trimOrNull($p['Category'] ?? null),
			lifecycleStatus: ManufacturingStatus::fromRaw($p['LifecycleStatus'] ?? null),
			stock: $this->parseStock($p),
			datasheetUrl: $this->trimOrNull($p['DataSheetUrl'] ?? null),
			isExactMatch: $exactMpn !== '' && strcasecmp($mpn, $exactMpn) === 0
		);
	}

	private function mapFull(array $p): InfoProviderResult
	{
		$mpn = (string)($p['ManufacturerPartNumber'] ?? '');
		$priceBreaks = $this->mapPriceBreaks($p['PriceBreaks'] ?? []);
		return new InfoProviderResult(
			source: $this->name,
			sourceSku: (string)($p['MouserPartNumber'] ?? ''),
			manufacturerName: (string)($p['Manufacturer'] ?? ''),
			manufacturerPartNumber: $mpn,
			description: $this->trimOrNull($p['Description'] ?? null),
			imageUrl: $this->trimOrNull($p['ImagePath'] ?? null),
			productUrl: $this->trimOrNull($p['ProductDetailUrl'] ?? null),
			packageName: $this->extractPackageName($p['ProductAttributes'] ?? []),
			categoryName: $this->trimOrNull($p['Category'] ?? null),
			lifecycleStatus: ManufacturingStatus::fromRaw($p['LifecycleStatus'] ?? null),
			stock: $this->parseStock($p),
			datasheetUrl: $this->trimOrNull($p['DataSheetUrl'] ?? null),
			currency: $this->resolveCurrency($p['PriceBreaks'] ?? []),
			parameters: $this->mapParameters($p['ProductAttributes'] ?? []),
			priceBreaks: $priceBreaks,
			rawSource: $p
		);
	}

	/**
	 * Stock: prefer the numeric `AvailabilityInStock` ("67"); fall back to the
	 * leading integer of the human `Availability` string ("67 In Stock")
	 */
	private function parseStock(array $p): ?int
	{
		$inStock = $p['AvailabilityInStock'] ?? null;
		if (is_string($inStock) && preg_match('/\d+/', $inStock, $m) === 1) {
			return (int)$m[0];
		}
		$avail = $p['Availability'] ?? null;
		if (is_string($avail) && preg_match('/\d[\d\s,.]*/', $avail, $m) === 1) {
			return (int)preg_replace('/\D/', '', $m[0]);
		}
		return null;
	}

	/**
	 * Mine a package/case label out of Mouser's ProductAttributes[]. Mouser
	 * keyword results carry mostly commercial attributes, so this often finds
	 * nothing — that's fine, packageName stays null.
	 */
	private function extractPackageName(array $attributes): ?string
	{
		$patterns = [
			'/\bpackage\s*\/\s*case\b/i',
			'/\bcase\s*\/\s*package\b/i',
			'/\bcase\s+code\b/i',
			'/\bcase\s+style\b/i',
			'/\bpackage\b/i'
		];
		foreach ($patterns as $pattern) {
			foreach ($attributes as $a) {
				$label = trim((string)($a['AttributeName'] ?? ''));
				$value = trim((string)($a['AttributeValue'] ?? ''));
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

	private function mapParameters(array $attributes): array
	{
		$out = [];
		foreach ($attributes as $a) {
			$name = trim((string)($a['AttributeName'] ?? ''));
			$value = trim((string)($a['AttributeValue'] ?? ''));
			if ($name === '' || $value === '' || $value === '-') {
				continue;
			}
			if (in_array($name, self::COMMERCIAL_ATTR_SKIP, true)) {
				continue;
			}
			$out[] = new Parameter(rawName: $name, rawValue: $value);
		}
		return $out;
	}

	/**
	 * Mouser PriceBreaks carry a *localized* price string ("2,79 €", "$2.79",
	 * "1.234,56 €") plus a quantity int. Parse the string to a float.
	 */
	private function mapPriceBreaks(array $priceBreaks): array
	{
		$out = [];
		foreach ($priceBreaks as $row) {
			$qty = $row['Quantity'] ?? null;
			$price = $this->parsePrice($row['Price'] ?? null);
			if ($qty === null || $price === null) {
				continue;
			}
			$out[] = new PriceBreak(quantity: (int)$qty, price: $price);
		}
		usort($out, fn(PriceBreak $a, PriceBreak $b) => $a->quantity <=> $b->quantity);
		return $out;
	}

	/**
	 * Parse a localized currency string to a float. Strips currency symbols and
	 * spaces, then infers the decimal separator: when both '.' and ',' appear,
	 * the LAST one is the decimal point and the other is the thousands group;
	 * a lone ',' is treated as the decimal point (EU format "2,79").
	 */
	private function parsePrice(mixed $raw): ?float
	{
		if (is_int($raw) || is_float($raw)) {
			return (float)$raw;
		}
		if (!is_string($raw) || $raw === '') {
			return null;
		}
		$s = preg_replace('/[^\d.,-]/', '', $raw);
		if ($s === '' || $s === null) {
			return null;
		}
		$lastDot = strrpos($s, '.');
		$lastComma = strrpos($s, ',');
		if ($lastDot !== false && $lastComma !== false) {
			// Both present: the rightmost is the decimal separator
			$decimalIsComma = $lastComma > $lastDot;
			$s = $decimalIsComma
				? str_replace('.', '', $s) // '.' = thousands
				: str_replace(',', '', $s); // ',' = thousands
			$s = str_replace(',', '.', $s);
		} elseif ($lastComma !== false) {
			// Only comma → EU decimal point
			$s = str_replace(',', '.', $s);
		}
		return is_numeric($s) ? (float)$s : null;
	}

	private function resolveCurrency(array $priceBreaks): string
	{
		foreach ($priceBreaks as $row) {
			$c = trim((string)($row['Currency'] ?? ''));
			if ($c !== '') {
				return $c;
			}
		}
		return $this->currency;
	}

	private function trimOrNull(?string $v): ?string
	{
		if ($v === null) {
			return null;
		}
		$v = trim($v);
		return $v === '' ? null : $v;
	}
}
