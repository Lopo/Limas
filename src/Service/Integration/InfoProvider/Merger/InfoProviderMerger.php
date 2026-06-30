<?php

namespace Limas\Service\Integration\InfoProvider\Merger;

use Limas\Service\Integration\InfoProvider\Dto\AggregatedPartCandidate;
use Limas\Service\Integration\InfoProvider\Dto\FieldWithProvenance;
use Limas\Service\Integration\InfoProvider\Dto\InfoProviderResult;
use Limas\Service\Integration\InfoProvider\Dto\InfoProviderSearchResult;
use Limas\Service\Integration\InfoProvider\Dto\ProviderSpecific;
use Limas\Service\ManufacturerCanonicalizer;


/**
 * Two responsibilities:
 *   - `groupKey(SearchResult)`: produce the (canonical-mfr, MPN) key that
 *      aggregator uses to bucket light search results across providers.
 *   - `mergeGroup(rowsBySource)`: take a pre-grouped set of FULL details and
 *      apply the MergeStrategy to produce one AggregatedPartCandidate with
 *      per-field provenance + conflict flags.
 *
 * Grouping operates on light SearchResult (cheap); merging needs the heavy
 * InfoProviderResult (parameters + priceBreaks).
 */
final class InfoProviderMerger
{
	public function __construct(
		private MergeStrategyInterface             $strategy,
		private readonly ManufacturerCanonicalizer $manufacturerCanonicalizer,
		private readonly SimilarityCollapser       $similarityCollapser = new SimilarityCollapser
	)
	{
	}

	/**
	 * Return a clone using a different strategy. Used for per-request overrides
	 * (e.g. the user picked Hierarchy with their own priority order from the
	 * aggregator settings dialog) without mutating the shared DI instance.
	 */
	public function withStrategy(MergeStrategyInterface $strategy): self
	{
		$clone = clone $this;
		$clone->strategy = $strategy;
		return $clone;
	}

	/**
	 * Manufacturer half of the key tries the alias table first (so "ONSEMI",
	 * "onsemi" and "ON Semiconductor" all collapse to the same canonical id);
	 * falls back to the same string normalisation when no alias matches yet.
	 * MPN side stays pure-string normalisation — there's no MPN alias table.
	 *
	 * When a source has the SKU but no manufacturer info (some TME house-line
	 * entries, OEMSecrets edge cases) we group per-source instead — the
	 * candidate stays visible in the aggregator UI as a single-source row.
	 * The alternative (returning null) used to silently drop these, which
	 * meant users searching for valid catalog items like "BS-244DSM-R" got
	 * "no results" even though TME's API had returned the product.
	 */
	public function groupKey(InfoProviderSearchResult $r): ?string
	{
		$mpn = ManufacturerCanonicalizer::normalize($r->manufacturerPartNumber);
		if ($mpn === '') {
			return null;
		}
		$canonical = $this->manufacturerCanonicalizer->canonicalize($r->manufacturerName);
		if ($canonical !== null) {
			return 'mfr:' . $canonical->getId() . '|' . $mpn;
		}
		$rawMfr = ManufacturerCanonicalizer::normalize($r->manufacturerName);
		if ($rawMfr !== '') {
			return 'raw:' . $rawMfr . '|' . $mpn;
		}
		// Manufacturer-less rows: group per-source so they appear as standalone
		// candidates that don't accidentally merge with unrelated SKUs that
		// happen to share the same MPN string in another distributor's catalog.
		return 'src:' . $r->source . '|' . $mpn;
	}

	/**
	 * Light-mode merge — fed only InfoProviderSearchResult per source (no
	 * Phase-2 detail fetch). Produces an AggregatedPartCandidate with the
	 * top-level fields the merger can already see in Phase 1
	 * (mfr/MPN/description/datasheetUrl/imageUrl/packageName) plus per-source
	 * `ProviderSpecific` carrying whatever the SearchResult already knew
	 * (sourceSku, productUrl, lifecycleStatus, categoryName, stock — NO
	 * currency or priceBreaks, those need Phase 2). `parameters` is empty;
	 * the FE deepens the row on user selection to fill it in.
	 *
	 * @param array<string, InfoProviderSearchResult> $rowsBySource
	 */
	public function mergeGroupLight(array $rowsBySource): AggregatedPartCandidate
	{
		$pick = fn(callable $extract): array => array_map($extract, $rowsBySource);
		$fields = [
			'manufacturerName' => $this->strategy->resolve($pick(fn(InfoProviderSearchResult $r) => $r->manufacturerName)),
			'manufacturerPartNumber' => $this->strategy->resolve($pick(fn(InfoProviderSearchResult $r) => $r->manufacturerPartNumber)),
			'description' => $this->resolveWithCollapse($pick(fn(InfoProviderSearchResult $r) => $r->description), 'description'),
			'datasheetUrl' => $this->strategy->resolve($pick(fn(InfoProviderSearchResult $r) => $r->datasheetUrl)),
			'imageUrl' => $this->strategy->resolve($pick(fn(InfoProviderSearchResult $r) => $r->imageUrl)),
			'packageName' => $this->resolveWithCollapse($pick(fn(InfoProviderSearchResult $r) => $r->packageName), 'package')
		];

		$specific = [];
		$parameters = [];
		foreach ($rowsBySource as $src => $r) {
			$specific[$src] = new ProviderSpecific(
				sourceSku: $r->sourceSku,
				productUrl: $r->productUrl,
				lifecycleStatus: $r->lifecycleStatus,
				categoryName: $r->categoryName,
				stock: $r->stock,
				currency: null,
				priceBreaks: []
			);
			$parameters[$src] = [];
		}

		$conflicts = [];
		foreach ($fields as $name => $fwp) {
			if ($fwp->isConflict) {
				$conflicts[] = $name;
			}
		}

		return new AggregatedPartCandidate(
			manufacturerName: $fields['manufacturerName'],
			manufacturerPartNumber: $fields['manufacturerPartNumber'],
			description: $fields['description'],
			datasheetUrl: $fields['datasheetUrl'],
			imageUrl: $fields['imageUrl'],
			packageName: $fields['packageName'],
			providerSpecific: $specific,
			parameters: $parameters,
			conflicts: $conflicts,
			contributingSources: array_keys($rowsBySource)
		);
	}

	/**
	 * @param array<string, InfoProviderResult> $rowsBySource one heavy detail per contributing provider
	 */
	public function mergeGroup(array $rowsBySource): AggregatedPartCandidate
	{
		$pick = fn(callable $extract): array => array_map($extract, $rowsBySource);

		$fields = [
			'manufacturerName' => $this->strategy->resolve($pick(fn(InfoProviderResult $r) => $r->manufacturerName)),
			'manufacturerPartNumber' => $this->strategy->resolve($pick(fn(InfoProviderResult $r) => $r->manufacturerPartNumber)),
			'description' => $this->resolveWithCollapse($pick(fn(InfoProviderResult $r) => $r->description), 'description'),
			'datasheetUrl' => $this->strategy->resolve($pick(fn(InfoProviderResult $r) => $r->datasheetUrl)),
			'imageUrl' => $this->strategy->resolve($pick(fn(InfoProviderResult $r) => $r->imageUrl)),
			'packageName' => $this->resolveWithCollapse($pick(fn(InfoProviderResult $r) => $r->packageName), 'package')
		];

		$specific = [];
		$parameters = [];
		foreach ($rowsBySource as $src => $r) {
			$specific[$src] = new ProviderSpecific(
				sourceSku: $r->sourceSku,
				productUrl: $r->productUrl,
				lifecycleStatus: $r->lifecycleStatus,
				categoryName: $r->categoryName,
				stock: $r->stock,
				currency: $r->currency,
				priceBreaks: $r->priceBreaks
			);
			$parameters[$src] = $r->parameters;
		}

		$conflicts = [];
		foreach ($fields as $name => $fwp) {
			if ($fwp->isConflict) {
				$conflicts[] = $name;
			}
		}

		return new AggregatedPartCandidate(
			manufacturerName: $fields['manufacturerName'],
			manufacturerPartNumber: $fields['manufacturerPartNumber'],
			description: $fields['description'],
			datasheetUrl: $fields['datasheetUrl'],
			imageUrl: $fields['imageUrl'],
			packageName: $fields['packageName'],
			providerSpecific: $specific,
			parameters: $parameters,
			conflicts: $conflicts,
			contributingSources: array_keys($rowsBySource)
		);
	}

	/**
	 * Run the per-field similarity collapse before voting, then preserve the
	 * ORIGINAL per-source values in the returned FieldWithProvenance so the
	 * Review dialog still shows what each distributor actually said. The
	 * chosenValue / isConflict / resolution come from the collapsed-input
	 * strategy run — equivalent values voted as one.
	 *
	 * @param array<string, ?string> $rawValues
	 */
	private function resolveWithCollapse(array $rawValues, string $field): FieldWithProvenance
	{
		$collapsed = match ($field) {
			'description' => $this->similarityCollapser->collapseDescriptions($rawValues),
			'package' => $this->similarityCollapser->collapsePackages($rawValues),
			default => $rawValues
		};
		$resolved = $this->strategy->resolve($collapsed);
		return new FieldWithProvenance(
			$resolved->chosenValue,
			$rawValues,
			$resolved->isConflict,
			$resolved->resolution
		);
	}
}
