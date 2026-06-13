<?php

namespace Limas\Service\Integration\InfoProvider\Adapter;

use Limas\Service\Integration\InfoProvider\Contract\InfoProviderInterface;
use Limas\Service\Integration\InfoProvider\Dto\InfoProviderResult;
use Limas\Service\Integration\InfoProvider\Dto\InfoProviderSearchResult;
use Limas\Service\Integration\InfoProvider\Dto\Parameter;
use Limas\Service\Integration\InfoProvider\Dto\PriceBreak;
use Limas\Service\Integration\InfoProvider\Enum\ManufacturingStatus;
use Limas\Service\Integration\InfoProvider\LcscService;
use Limas\Service\Integration\InfoProvider\ProviderCapability;


/**
 * Adapts LCSC / JLCPCB catalog data into InfoProviderResult
 *
 * Two-phase shape:
 *  - Phase 1 (`searchByMpnAsync`) hits **jlcsearch**, which returns one row
 *    per part variant carrying just `{lcsc, mfr, package, is_basic, stock,
 *    price}`. The MFR field is the manufacturer PART NUMBER, NOT the
 *    manufacturer name — that and the rest of the parametric data come
 *    from Phase 2.
 *
 *  - Phase 2 (`getDetailsAsync`) hits **wmsc.lcsc.com** per LCSC code with
 *    the rich product detail (price ladder, datasheet PDF, parameters,
 *    images, English manufacturer name in `brandNameEn`).
 *
 * Cost: Phase 1 = 1 HTTP per query; Phase 2 = N HTTP per candidate. wmsc
 * has no batched endpoint, so N candidates → N fan-out calls run in
 * parallel via Symfony HttpClient's curl_multi (same pattern as
 * DigiKey/Farnell direct-vendor adapters).
 *
 * SourceSku: the LCSC product code (`C2128`), uppercased. Unique identifier
 * across the catalog; used as Phase-2 key and the SKU displayed in the UI's
 * per-distributor detail panel.
 *
 * Manufacturer canonicalisation: wmsc's `brandNameEn` is sometimes a SHORT
 * code (e.g. "JSCJ" for Jiangsu Changjing Electronics). The ManufacturerAlias
 * table handles this on the canonicalisation side — adapter just passes the
 * raw `brandNameEn` through and lets the aggregator's grouping take care of
 * matching aliases.
 */
final class LcscAdapter
	implements InfoProviderInterface
{
	public function __construct(
		private readonly LcscService $service,
		private readonly string      $currency = 'USD'
	)
	{
	}

	public function getName(): string
	{
		return 'lcsc';
	}

	public function isConfigured(): bool
	{
		return $this->service->isConfigured();
	}

	public function getCapabilities(): array
	{
		// Phase-2 response has everything except a true FOOTPRINT field —
		// "encapStandard" is closer to package-type (SOD-323, SOIC-8)
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
		// Phase 1 is JUST the jlcsearch keyword call here. We fan out the
		// per-LCSC-code wmsc lookups inside `mapSearchByMpnResponses` —
		// after the search has resolved we know which codes to ask for,
		// and curl_multi lets us fire them all in parallel.
		return ['search' => $this->service->searchAsync($mpn, $limit)];
	}

	public function mapSearchByMpnResponses(array $responses, string $mpn, int $limit = 10): array
	{
		if (!isset($responses['search'])) {
			return [];
		}
		$data = $this->service->awaitSearch($responses['search']);
		$components = array_slice($data['components'], 0, $limit);
		if ($components === []) {
			return [];
		}

		// Enrichment fan-out: jlcsearch's light row has the MPN but NOT
		// the manufacturer name. The merger's groupKey() drops rows
		// with empty `manufacturerName` (it can't form a canonical key
		// without one), so we'd lose every LCSC result through grouping
		// unless we resolve the name first.
		//
		// Trade-off: this adds N parallel HTTP calls to Phase 1 instead
		// of saving them for Phase 2. But Phase 2 is going to fire them
		// anyway, and the service's `awaitDetail()` caches per-code so
		// the Phase-2 calls become cache hits — net: same total HTTP,
		// faster wallclock because the calls run during Phase 1 in
		// parallel with the OTHER providers' Phase-1 calls.
		$pendingDetails = [];   // code => ResponseInterface
		foreach ($components as $c) {
			$code = $this->lcscCode($c);
			if ($code !== '') {
				$pendingDetails[$code] = $this->service->getDetailAsync($code);
			}
		}
		$detailsByCode = [];
		foreach ($pendingDetails as $code => $response) {
			$detail = $this->service->awaitDetail($response);
			if ($detail !== null) {
				$detailsByCode[$code] = $detail;
			}
		}

		return array_map(function (array $c) use ($mpn, $detailsByCode) {
			$code = $this->lcscCode($c);
			$detail = $detailsByCode[$code] ?? null;
			return $this->mapLight($c, $detail, exactMpn: $mpn);
		}, $components);
	}

	public function getDetails(string $sourceSku): ?InfoProviderResult
	{
		return $this->mapDetailsResponses($this->getDetailsAsync($sourceSku), $sourceSku);
	}

	public function getDetailsAsync(string $sourceSku): array
	{
		return ['detail' => $this->service->getDetailAsync($sourceSku)];
	}

	public function mapDetailsResponses(array $responses, string $sourceSku): ?InfoProviderResult
	{
		if (!isset($responses['detail'])) {
			return null;
		}
		$result = $this->service->awaitDetail($responses['detail']);
		return $result === null ? null : $this->mapFull($result);
	}

	public function getDetailsBatchAsync(array $sourceSkus): array
	{
		// Phase 1 already warmed the per-code wmsc cache via the
		// enrichment fan-out. Anything that's a cache hit costs zero
		// HTTP here; the rest fan out via curl_multi.
		$out = [];
		foreach ($sourceSkus as $sku) {
			if ($this->service->getCachedDetail($sku) !== null) {
				continue;
			}
			$out['detail.' . $sku] = $this->service->getDetailAsync($sku);
		}
		return $out;
	}

	public function mapDetailsBatchResponses(array $responses, array $sourceSkus): array
	{
		$out = [];
		foreach ($sourceSkus as $sku) {
			$cached = $this->service->getCachedDetail($sku);
			if ($cached !== null) {
				$out[$sku] = $this->mapFull($cached);
				continue;
			}
			$key = 'detail.' . $sku;
			if (!isset($responses[$key])) {
				$out[$sku] = null;
				continue;
			}
			$result = $this->service->awaitDetail($responses[$key]);
			$out[$sku] = $result === null ? null : $this->mapFull($result);
		}
		return $out;
	}

	public function searchExactByMpnAsync(string $mpn): array
	{
		// jlcsearch is keyword-fuzzy ("1N4148" returns 1N4148WS, 1N4148WT
		// variants too). For the strict completion-pattern lookup we run
		// the same call and filter mfr === mpn in the mapper.
		return ['exact' => $this->service->searchAsync($mpn, 50)];
	}

	public function mapSearchExactByMpnResponses(array $responses, string $mpn): array
	{
		if (!isset($responses['exact'])) {
			return [];
		}
		$data = $this->service->awaitSearch($responses['exact']);
		$strict = [];
		foreach ($data['components'] as $c) {
			if (strcasecmp((string)($c['mfr'] ?? ''), $mpn) === 0) {
				$strict[] = $c;
			}
		}
		if ($strict === []) {
			return [];
		}
		// Mirror the Phase-1 enrichment so the completion pass sees rows
		// with a populated manufacturer name (otherwise groupKey() would
		// drop them just like in the regular search path)
		$pendingDetails = [];
		foreach ($strict as $c) {
			$code = $this->lcscCode($c);
			if ($code !== '') {
				$pendingDetails[$code] = $this->service->getDetailAsync($code);
			}
		}
		$detailsByCode = [];
		foreach ($pendingDetails as $code => $response) {
			$detail = $this->service->awaitDetail($response);
			if ($detail !== null) {
				$detailsByCode[$code] = $detail;
			}
		}
		return array_map(function (array $c) use ($mpn, $detailsByCode) {
			$code = $this->lcscCode($c);
			return $this->mapLight($c, $detailsByCode[$code] ?? null, exactMpn: $mpn);
		}, $strict);
	}

	/**
	 * `$detail` is the wmsc.lcsc.com full-product row enriched in Phase 1
	 * (may be null when the wmsc lookup failed for this code). When
	 * present, we lift `brandNameEn` into `manufacturerName` so the
	 * merger's groupKey() can form a canonical key for this candidate.
	 */
	private function mapLight(array $c, ?array $detail = null, string $exactMpn = ''): InfoProviderSearchResult
	{
		$mpn = (string)($c['mfr'] ?? '');
		$lcscCode = $this->lcscCode($c);
		$desc = $this->trimOrNull($c['description'] ?? null);
		$mfrName = $detail !== null ? ($this->trimOrNull($detail['brandNameEn'] ?? null) ?? '') : '';
		// jlcsearch's description is often empty — fall back to wmsc's
		// productDescEn when available so the candidate row in the UI
		// shows something useful even before Phase-2 detail merge.
		if ($desc === null && $detail !== null) {
			$desc = $this->trimOrNull($detail['productDescEn'] ?? null);
		}
		return new InfoProviderSearchResult(
			source: $this->getName(),
			sourceSku: $lcscCode,
			manufacturerName: $mfrName,
			manufacturerPartNumber: $mpn,
			description: $desc,
			imageUrl: null,
			productUrl: $lcscCode !== '' ? "https://www.lcsc.com/product-detail/{$lcscCode}.html" : null,
			packageName: $this->trimOrNull($c['package'] ?? null),
			categoryName: null,
			lifecycleStatus: null,
			stock: isset($c['stock']) ? (int)$c['stock'] : null,
			datasheetUrl: $detail !== null ? $this->trimOrNull($detail['pdfUrl'] ?? null) : null,
			isExactMatch: $exactMpn !== '' && strcasecmp($mpn, $exactMpn) === 0
		);
	}

	/**
	 * Map a wmsc.lcsc.com detail row into an InfoProviderResult. The
	 * envelope-unwrapping happened in `LcscService::awaitDetail`, so this
	 * receives the flat `result` dict.
	 */
	private function mapFull(array $r): InfoProviderResult
	{
		$mpn = (string)($r['productModel'] ?? '');
		$lcscCode = (string)($r['productCode'] ?? '');
		$datasheet = (string)($r['pdfUrl'] ?? '');
		$image = null;
		$images = $r['productImages'] ?? null;
		if (is_array($images) && $images !== []) {
			// productImages is a list of strings (URL) on this endpoint.
			// First image wins — typically the front of the part.
			$first = $images[0];
			if (is_string($first) && $first !== '') {
				$image = $first;
			}
		}
		$description = $this->trimOrNull($r['productDescEn'] ?? null);

		return new InfoProviderResult(
			source: $this->getName(),
			sourceSku: $lcscCode,
			manufacturerName: $this->trimOrNull($r['brandNameEn'] ?? null) ?? '',
			manufacturerPartNumber: $mpn,
			description: $description,
			imageUrl: $image,
			productUrl: $lcscCode !== '' ? "https://www.lcsc.com/product-detail/{$lcscCode}.html" : null,
			packageName: $this->trimOrNull($r['encapStandard'] ?? null),
			categoryName: $this->trimOrNull($r['parentCatalogName'] ?? null),
			lifecycleStatus: ($r['isPreSale'] ?? false) === true ? ManufacturingStatus::PreRelease : null,
			stock: isset($r['stockNumber']) ? (int)$r['stockNumber'] : null,
			datasheetUrl: $datasheet !== '' ? $datasheet : null,
			currency: $this->currency,
			parameters: $this->mapParameters($r['paramVOList'] ?? []),
			priceBreaks: $this->mapPriceBreaks($r['productPriceList'] ?? []),
			rawSource: [
				'lcsc_code' => $lcscCode,
				'is_basic' => $r['isHot'] ?? null,
				'rohs' => $r['isEnvironment'] ?? null
			]
		);
	}

	/**
	 * `paramVOList` carries parameters as `{paramNameEn, paramValueEn,
	 * isMain, sortNumber}`. We keep all of them — `isMain=false` ones
	 * are still useful for value-parser min/max routing (the Stage-2
	 * parser maps "Voltage - DC Reverse (Vr) (Max)" onto the canonical
	 * "Voltage - DC Reverse (Vr)" + qualifier=max).
	 *
	 * Drop placeholder rows (`-`, empty) so the alias table doesn't get
	 * polluted with no-content rows.
	 */
	private function mapParameters(array $params): array
	{
		$out = [];
		foreach ($params as $p) {
			if (!is_array($p)) {
				continue;
			}
			$name = $this->trimOrNull($p['paramNameEn'] ?? null);
			$value = $this->trimOrNull($p['paramValueEn'] ?? null);
			if ($name === null || $value === null || $value === '-') {
				continue;
			}
			$out[] = new Parameter(rawName: $name, rawValue: $value);
		}
		return $out;
	}

	/**
	 * `productPriceList` rows: `{ladder, productPrice, usdPrice, currencyPrice,
	 * currencySymbol, ...}`. We use `usdPrice` (LCSC's primary catalog price)
	 * and let the aggregator stamp the currency at the candidate level —
	 * cross-source price comparison is meaningful only when we know the
	 * units are uniform.
	 */
	private function mapPriceBreaks(array $rows): array
	{
		$out = [];
		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}
			$qty = $row['ladder'] ?? null;
			$price = $row['usdPrice'] ?? ($row['currencyPrice'] ?? null);
			if ($qty === null || $price === null) {
				continue;
			}
			$out[] = new PriceBreak(quantity: (int)$qty, price: (float)$price);
		}
		usort($out, fn(PriceBreak $a, PriceBreak $b) => $a->quantity <=> $b->quantity);
		return $out;
	}

	/**
	 * jlcsearch returns `lcsc` as the numeric ID without the "C" prefix.
	 * Restore the prefix so it matches LCSC's catalog UI + can drive
	 * wmsc's `productCode` query in Phase 2.
	 */
	private function lcscCode(array $c): string
	{
		$id = $c['lcsc'] ?? null;
		if ($id === null || $id === '') {
			return '';
		}
		return 'C' . $id;
	}

	private function trimOrNull(mixed $value): ?string
	{
		if (!is_string($value)) {
			return null;
		}
		$trimmed = trim($value);
		return $trimmed === '' ? null : $trimmed;
	}
}
