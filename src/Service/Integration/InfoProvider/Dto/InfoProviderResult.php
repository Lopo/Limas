<?php

namespace Limas\Service\Integration\InfoProvider\Dto;


/**
 * Heavy per-source product detail — what each adapter returns from
 * `getDetails($sourceSku)`. Extends the lightweight `InfoProviderSearchResult`
 * with the expensive bits: parameters, price breaks, currency, raw payload.
 *
 * The Merger consumes these to build `AggregatedPartCandidate` (per-field
 * merge + conflict detection across providers).
 */
class InfoProviderResult
	extends InfoProviderSearchResult
{
	public function __construct(
		string                                                            $source,
		string                                                            $sourceSku,
		string                                                            $manufacturerName,
		string                                                            $manufacturerPartNumber,
		?string                                                           $description = null,
		?string                                                           $imageUrl = null,
		?string                                                           $productUrl = null,
		?string                                                           $packageName = null,
		?string                                                           $categoryName = null,
		?\Limas\Service\Integration\InfoProvider\Enum\ManufacturingStatus $lifecycleStatus = null,
		?int                                                              $stock = null,
		?string                                                           $datasheetUrl = null,
		bool                                                              $isExactMatch = false,
		public readonly ?string                                           $currency = null,
		/** @var Parameter[] */
		public readonly array                                             $parameters = [],
		/** @var PriceBreak[] */
		public readonly array                                             $priceBreaks = [],
		/** Whole raw payload from the provider for debugging / future fields */
		public readonly ?array                                            $rawSource = null
	)
	{
		parent::__construct(
			$source,
			$sourceSku,
			$manufacturerName,
			$manufacturerPartNumber,
			$description,
			$imageUrl,
			$productUrl,
			$packageName,
			$categoryName,
			$lifecycleStatus,
			$stock,
			$datasheetUrl,
			$isExactMatch
		);
	}
}
