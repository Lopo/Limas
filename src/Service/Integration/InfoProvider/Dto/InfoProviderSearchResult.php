<?php

namespace Limas\Service\Integration\InfoProvider\Dto;


/**
 * Lightweight per-source search hit — what each adapter returns from
 * `searchByMpn()`. Carries enough to render a candidate list and to group
 * across providers (manufacturer + MPN + basic descriptors) but no parameters
 * or pricing — those come via `getDetails()` which yields the heavier
 * `InfoProviderResult`.
 *
 * Two-phase rationale: large keyword searches (1N4148 → 50+ matches) become
 * cheap; the heavy detail call only fires for the candidate(s) the user
 * actually picks or that the aggregator merges.
 */
class InfoProviderSearchResult
{
	public function __construct(
		public readonly string                                                            $source, // 'tme', 'digikey', 'farnell', 'mouser', 'octopart'
		public readonly string                                                            $sourceSku, // SKU within the source — feed back to getDetails($sourceSku)
		public readonly string                                                            $manufacturerName,
		public readonly string                                                            $manufacturerPartNumber,
		public readonly ?string                                                           $description = null,
		public readonly ?string                                                           $imageUrl = null,
		public readonly ?string                                                           $productUrl = null,
		public readonly ?string                                                           $packageName = null,
		public readonly ?string                                                           $categoryName = null,
		public readonly ?\Limas\Service\Integration\InfoProvider\Enum\ManufacturingStatus $lifecycleStatus = null,
		public readonly ?int                                                              $stock = null,
		public readonly ?string                                                           $datasheetUrl = null, // kept here because Merger compares it across sources
		public readonly bool                                                              $isExactMatch = false // adapter best-effort hint for UI ranking
	)
	{
	}
}
