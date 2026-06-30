<?php

namespace Limas\Service\Integration\InfoProvider\Adapter;

use Limas\Service\Integration\InfoProvider\Contract\InfoProviderInterface;
use Limas\Service\Integration\InfoProvider\Dto\InfoProviderResult;
use Limas\Service\Integration\InfoProvider\Dto\InfoProviderSearchResult;
use Limas\Service\Integration\InfoProvider\Dto\Parameter;
use Limas\Service\Integration\InfoProvider\Dto\PriceBreak;
use Limas\Service\Integration\InfoProvider\Enum\ManufacturingStatus;
use Limas\Service\Integration\InfoProvider\OemSecretsService;
use Limas\Service\Integration\InfoProvider\ProviderCapability;


/**
 * Adapts the OEMSecrets Part Search API into the aggregator's two-phase
 * InfoProviderInterface
 *
 * OEMSecrets is itself a meta-aggregator (33+ downstream distributors per
 * 1N4148 query), so this adapter has TWO unusual jobs vs. the direct-vendor
 * adapters (Farnell/DigiKey/TME):
 *
 *  1. **Filter out distributors we already query directly.** Otherwise
 *     DigiKey would show twice (direct adapter + OEMSecrets-via-DigiKey)
 *     with potentially stale OEMSecrets data conflicting with our direct
 *     read. The filter list is built dynamically from the other configured
 *     InfoProvider adapters: their `getName()` values become the set of
 *     names we drop. OEMSecrets then contributes data ONLY from sources
 *     we can't reach otherwise (Future, Avnet, TTI, Verical, EBV,
 *     Ozdisan, …). Disabling a direct adapter (no API key) automatically
 *     re-enables that distributor's coverage via OEMSecrets.
 *
 *  2. **Phase-2 detail fetch hits no network.** OEMSecrets's testing tier
 *     has a 10-req/day quota — we'd burn it instantly running a normal
 *     "search returns light, detail re-fetches full" pattern. Instead,
 *     `searchByMpnAsync` already returns full per-part data; we cache it
 *     per-(part, distributor) via the service's `awaitAndCache()`, and
 *     `mapDetailsBatchResponses` reads from that cache without firing a
 *     second HTTP call.
 *
 * Per-group pick: a single OEMSecrets query for "1N4148" returns ~1000
 * rows grouped into ~250 unique (mfr, MPN) variants. Per group we pick
 * the row with the highest `quantity_in_stock`, preferring entries with
 * `distributor_authorisation_status == 'authorised'`. That's the row the
 * aggregator merge sees from this source.
 */
final class OemSecretsAdapter
	implements InfoProviderInterface
{
	/**
	 * Lazy-built set of OTHER configured adapters' names — the
	 * `distributor_common_name` values we want to filter OUT of
	 * OEMSecrets results because we read them directly elsewhere.
	 *
	 * @var array<string, true>|null
	 */
	private ?array $directDistributorSet = null;


	/**
	 * @param iterable<InfoProviderInterface> $directAdapters All
	 *        tagged InfoProvider adapters in the container. We iterate
	 *        them lazily on first filter call and skip self ($this).
	 */
	public function __construct(
		private readonly OemSecretsService $service,
		private readonly iterable          $directAdapters,
		private readonly string            $currency = 'EUR'
	)
	{
	}

	public function getName(): string
	{
		return 'oemsecrets';
	}

	public function isConfigured(): bool
	{
		return $this->service->isConfigured();
	}

	public function getCapabilities(): array
	{
		// Response carries everything the merger needs except a true
		// FOOTPRINT field; "packaging" is package-as-shipped (tape/reel/cut),
		// not the IC footprint, so we don't claim FOOTPRINT here
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
		return ['search' => $this->service->searchByMpnAsync($mpn)];
	}

	public function mapSearchByMpnResponses(array $responses, string $mpn, int $limit = 10): array
	{
		$data = $this->service->awaitAndCache($responses['search']);
		$stock = $data['stock'] ?? [];
		if ($stock === []) {
			return [];
		}

		// Group by (manufacturer, part_number). Each group represents one
		// physical part variant; OEMSecrets lists it under every distributor
		// that carries it. We collapse to one row per group below.
		/** @var array<string, array<int, array<string, mixed>>> $groups */
		$groups = [];
		foreach ($stock as $row) {
			$dist = $this->distributorName($row);
			if ($dist === '' || $this->isDirect($dist)) {
				continue;
			}
			$mfr = (string)($row['manufacturer'] ?? '');
			$pn = (string)($row['part_number'] ?? '');
			if ($mfr === '' || $pn === '') {
				continue;
			}
			$key = strtolower($mfr . '|' . $pn);
			$groups[$key][] = $row;
		}
		if ($groups === []) {
			return [];
		}

		// Per group: prefer authorised distributor lines with highest
		// quantity_in_stock. OEMSecrets returns `quantity_in_stock` as
		// either int or string depending on the source — coerce.
		$picked = [];
		foreach ($groups as $rows) {
			usort($rows, static function (array $a, array $b): int {
				$authA = ($a['distributor_authorisation_status'] ?? '') === 'authorised' ? 1 : 0;
				$authB = ($b['distributor_authorisation_status'] ?? '') === 'authorised' ? 1 : 0;
				if ($authA !== $authB) {
					return $authB - $authA;
				}
				$stockA = (int)($a['quantity_in_stock'] ?? 0);
				$stockB = (int)($b['quantity_in_stock'] ?? 0);
				return $stockB - $stockA;
			});
			$picked[] = $rows[0];
		}

		// Rank groups: exact MPN match first, then highest selected-row stock — matches the aggregator-level sort
		usort($picked, function (array $a, array $b) use ($mpn): int {
			$exactA = strcasecmp((string)($a['part_number'] ?? ''), $mpn) === 0 ? 1 : 0;
			$exactB = strcasecmp((string)($b['part_number'] ?? ''), $mpn) === 0 ? 1 : 0;
			if ($exactA !== $exactB) {
				return $exactB - $exactA;
			}
			return ((int)($b['quantity_in_stock'] ?? 0)) - ((int)($a['quantity_in_stock'] ?? 0));
		});

		return array_map(
			fn(array $row) => $this->mapLight($row, exactMpn: $mpn),
			array_slice($picked, 0, $limit)
		);
	}

	public function getDetails(string $sourceSku): ?InfoProviderResult
	{
		return $this->mapDetailsResponses([], $sourceSku);
	}

	/**
	 * OEMSecrets Phase-2 doesn't dispatch — the data we need was already
	 * pulled by Phase-1 and cached per (part, distributor). Returning an
	 * empty pending map lets the aggregator's drain step call
	 * `mapDetailsBatchResponses` with no responses; we read from cache.
	 */
	public function getDetailsAsync(string $sourceSku): array
	{
		return [];
	}

	public function mapDetailsResponses(array $responses, string $sourceSku): ?InfoProviderResult
	{
		$row = $this->service->getCachedPart($sourceSku);
		return $row === null ? null : $this->mapFull($row);
	}

	public function getDetailsBatchAsync(array $sourceSkus): array
	{
		// Same reason as `getDetailsAsync`. The aggregator's drain step
		// will still call `mapDetailsBatchResponses` with an empty
		// response map + the requested SKUs.
		return [];
	}

	public function mapDetailsBatchResponses(array $responses, array $sourceSkus): array
	{
		$out = [];
		foreach ($sourceSkus as $sku) {
			$row = $this->service->getCachedPart($sku);
			$out[$sku] = $row === null ? null : $this->mapFull($row);
		}
		return $out;
	}

	public function searchExactByMpnAsync(string $mpn): array
	{
		// OEMSecrets's keyword endpoint already accepts an MPN — it returns
		// fuzzy variants too (1N4148 → 1N4148WS, 1N4148WT, …), which is
		// what the regular search uses. For "exact" we hit the same call
		// and filter strictly to `part_number == mpn` in the mapping step.
		return ['exact' => $this->service->searchByMpnAsync($mpn)];
	}

	public function mapSearchExactByMpnResponses(array $responses, string $mpn): array
	{
		if (!isset($responses['exact'])) {
			return [];
		}
		$data = $this->service->awaitAndCache($responses['exact']);
		$stock = $data['stock'] ?? [];
		if ($stock === []) {
			return [];
		}
		// Strict MPN filter + drop direct-distributor rows (same as
		// regular search), then per (mfr) collapse to one row.
		/** @var array<string, array<string, mixed>> $byMfr */
		$byMfr = [];
		foreach ($stock as $row) {
			if (strcasecmp((string)($row['part_number'] ?? ''), $mpn) !== 0) {
				continue;
			}
			$dist = $this->distributorName($row);
			if ($dist === '' || $this->isDirect($dist)) {
				continue;
			}
			$mfr = (string)($row['manufacturer'] ?? '');
			if ($mfr === '') {
				continue;
			}
			$key = strtolower($mfr);
			$prev = $byMfr[$key] ?? null;
			$prevStock = $prev === null ? -1 : (int)($prev['quantity_in_stock'] ?? 0);
			$curStock = (int)($row['quantity_in_stock'] ?? 0);
			if ($prev === null || $curStock > $prevStock) {
				$byMfr[$key] = $row;
			}
		}
		return array_map(fn(array $row) => $this->mapLight($row, exactMpn: $mpn), array_values($byMfr));
	}

	private function mapLight(array $row, string $exactMpn = ''): InfoProviderSearchResult
	{
		$mpn = (string)($row['part_number'] ?? '');
		$dist = $this->distributorName($row);
		$sku = $this->service->cacheKeyFor($mpn, $dist);
		return new InfoProviderSearchResult(
			source: $this->getName(),
			sourceSku: $sku,
			manufacturerName: (string)($row['manufacturer'] ?? ''),
			manufacturerPartNumber: $mpn,
			description: $this->trimOrNull($row['description'] ?? null),
			imageUrl: $this->trimOrNull($row['image_url'] ?? null),
			productUrl: $this->trimOrNull($row['buy_now_url'] ?? null),
			// `packaging` is shipping format ("Tape and Reel", "Cut Tape"),
			// NOT the IC footprint — don't pollute packageName with it
			packageName: null,
			categoryName: $this->trimOrNull($row['category'] ?? null),
			lifecycleStatus: ManufacturingStatus::fromRaw($this->trimOrNull($row['life_cycle'] ?? null)),
			stock: isset($row['quantity_in_stock']) ? (int)$row['quantity_in_stock'] : null,
			datasheetUrl: $this->trimOrNull($row['datasheet_url'] ?? null),
			isExactMatch: $exactMpn !== '' && strcasecmp($mpn, $exactMpn) === 0
		);
	}

	private function mapFull(array $row): InfoProviderResult
	{
		$mpn = (string)($row['part_number'] ?? '');
		$dist = $this->distributorName($row);
		$sku = $this->service->cacheKeyFor($mpn, $dist);
		return new InfoProviderResult(
			source: $this->getName(),
			sourceSku: $sku,
			manufacturerName: (string)($row['manufacturer'] ?? ''),
			manufacturerPartNumber: $mpn,
			description: $this->trimOrNull($row['description'] ?? null),
			imageUrl: $this->trimOrNull($row['image_url'] ?? null),
			productUrl: $this->trimOrNull($row['buy_now_url'] ?? null),
			// `packaging` is shipping format ("Tape and Reel", "Cut Tape"),
			// NOT the IC footprint — don't pollute packageName with it
			packageName: null,
			categoryName: $this->trimOrNull($row['category'] ?? null),
			lifecycleStatus: ManufacturingStatus::fromRaw($this->trimOrNull($row['life_cycle'] ?? null)),
			stock: isset($row['quantity_in_stock']) ? (int)$row['quantity_in_stock'] : null,
			datasheetUrl: $this->trimOrNull($row['datasheet_url'] ?? null),
			currency: $this->currency,
			parameters: $this->mapParameters($row),
			priceBreaks: $this->mapPriceBreaks($row['prices'] ?? []),
			rawSource: ['oemsecrets_distributor' => $dist, 'row' => $row]
		);
	}

	/**
	 * OEMSecrets parameters come embedded in `compliance` + a handful of
	 * standalone fields; there's no generic attribute array like Farnell.
	 * We expose just the compliance flags (RoHS, Pb status) and lead time
	 * — useful for filtering, less useful for canonical parameter merge.
	 */
	private function mapParameters(array $row): array
	{
		$out = [];
		$compliance = is_array($row['compliance'] ?? null) ? $row['compliance'] : [];
		if (isset($compliance['rohs'])) {
			$out[] = new Parameter(rawName: 'RoHS', rawValue: (bool)$compliance['rohs'] ? 'Yes' : 'No');
		}
		$pb = $compliance['pb_status'] ?? null;
		if (is_string($pb) && $pb !== '') {
			$out[] = new Parameter(rawName: 'Lead-free Status', rawValue: $pb);
		}
		$leadWeeks = $row['lead_time_weeks'] ?? null;
		if ($leadWeeks !== null && $leadWeeks !== '' && (int)$leadWeeks > 0) {
			$out[] = new Parameter(rawName: 'Lead Time (weeks)', rawValue: (string)$leadWeeks);
		}
		$moq = $row['moq'] ?? null;
		if ($moq !== null && $moq !== '' && (int)$moq > 0) {
			$out[] = new Parameter(rawName: 'MOQ', rawValue: (string)$moq);
		}
		return $out;
	}

	/**
	 * Prices come as `{currency: [{unit_break, unit_price}, ...]}`. We
	 * prefer our configured target currency; if missing, fall back to the
	 * row's `source_currency` ladder so the user still sees a price column
	 * (just in the original currency).
	 */
	private function mapPriceBreaks(array $prices): array
	{
		$ladder = $prices[$this->currency] ?? $prices[strtoupper($this->currency)] ?? null;
		if (!is_array($ladder) || $ladder === []) {
			// Fall back to whichever currency the response contains first.
			foreach ($prices as $rows) {
				if (is_array($rows) && $rows !== []) {
					$ladder = $rows;
					break;
				}
			}
		}
		if (!is_array($ladder)) {
			return [];
		}
		$out = [];
		foreach ($ladder as $row) {
			$qty = $row['unit_break'] ?? null;
			$price = $row['unit_price'] ?? null;
			if ($qty === null || $price === null) {
				continue;
			}
			$out[] = new PriceBreak(quantity: (int)$qty, price: (float)$price);
		}
		usort($out, fn(PriceBreak $a, PriceBreak $b) => $a->quantity <=> $b->quantity);
		return $out;
	}

	private function trimOrNull(mixed $value): ?string
	{
		if (!is_string($value)) {
			return null;
		}
		$trimmed = trim($value);
		return $trimmed === '' ? null : $trimmed;
	}

	/**
	 * OEMSecrets's distributor object has both `distributor_common_name`
	 * (canonicalised, our preferred identity) and `distributor_name`
	 * (raw vendor string). When the common name is blank we fall back to
	 * the raw — but never to an empty string, since we filter on this.
	 */
	private function distributorName(array $row): string
	{
		$dist = $row['distributor'] ?? null;
		if (!is_array($dist)) {
			return '';
		}
		$common = (string)($dist['distributor_common_name'] ?? '');
		return $common !== '' ? $common : (string)($dist['distributor_name'] ?? '');
	}

	/**
	 * True if `$oemSecretsDistributorName` corresponds to an OTHER
	 * configured InfoProvider adapter — i.e. a source we already query
	 * directly. We match by first alphanumeric "word" of the lowercased
	 * common name: "Mouser Electronics" → "mouser" → matches an adapter
	 * named "mouser"; "Arrow Electronics" → "arrow" → no adapter named
	 * "arrow" → keep the row.
	 *
	 * Adapters that are wired but NOT configured (e.g. Mouser without an
	 * API key) DON'T filter — that way OEMSecrets can transparently fill
	 * the gap for vendors the operator hasn't set up direct credentials
	 * for.
	 */
	private function isDirect(string $oemSecretsDistributorName): bool
	{
		$set = $this->getDirectDistributorSet();
		if ($set === []) {
			return false;
		}
		$normalized = strtolower($oemSecretsDistributorName);
		if (preg_match('/[a-z0-9]+/', $normalized, $m) !== 1) {
			return false;
		}
		return isset($set[$m[0]]);
	}

	/**
	 * @return array<string, true>
	 */
	private function getDirectDistributorSet(): array
	{
		if ($this->directDistributorSet !== null) {
			return $this->directDistributorSet;
		}
		$set = [];
		foreach ($this->directAdapters as $adapter) {
			if ($adapter === $this) {
				continue;
			}
			if (!$adapter->isConfigured()) {
				// Unconfigured adapters don't contribute to the filter —
				// let OEMSecrets fill the gap for that distributor.
				continue;
			}
			$set[strtolower($adapter->getName())] = true;
		}
		return $this->directDistributorSet = $set;
	}
}
