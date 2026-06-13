<?php

namespace Limas\Service\Integration\InfoProvider\Merger;

use Limas\Service\Integration\InfoProvider\Dto\AggregatedPartCandidate;
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
		private readonly ManufacturerCanonicalizer $manufacturerCanonicalizer
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
	 */
	public function groupKey(InfoProviderSearchResult $r): ?string
	{
		$mpn = ManufacturerCanonicalizer::normalize($r->manufacturerPartNumber);
		if ($mpn === '') {
			return null;
		}
		$canonical = $this->manufacturerCanonicalizer->canonicalize($r->manufacturerName);
		$mfrKey = $canonical !== null
			? 'mfr:' . $canonical->getId()
			: 'raw:' . ManufacturerCanonicalizer::normalize($r->manufacturerName);
		if ($mfrKey === 'raw:') {
			return null;
		}
		return "$mfrKey|$mpn";
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
			'description' => $this->strategy->resolve($pick(fn(InfoProviderSearchResult $r) => $r->description)),
			'datasheetUrl' => $this->strategy->resolve($pick(fn(InfoProviderSearchResult $r) => $r->datasheetUrl)),
			'imageUrl' => $this->strategy->resolve($pick(fn(InfoProviderSearchResult $r) => $r->imageUrl)),
			'packageName' => $this->strategy->resolve($pick(fn(InfoProviderSearchResult $r) => $r->packageName))
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
			'description' => $this->strategy->resolve($pick(fn(InfoProviderResult $r) => $r->description)),
			'datasheetUrl' => $this->strategy->resolve($pick(fn(InfoProviderResult $r) => $r->datasheetUrl)),
			'imageUrl' => $this->strategy->resolve($pick(fn(InfoProviderResult $r) => $r->imageUrl)),
			'packageName' => $this->strategy->resolve($pick(fn(InfoProviderResult $r) => $r->packageName))
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
}
