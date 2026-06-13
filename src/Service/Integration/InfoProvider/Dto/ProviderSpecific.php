<?php

namespace Limas\Service\Integration\InfoProvider\Dto;


/**
 * Bag of fields that are NOT merged across distributors because each distributor
 * owns them by definition (stock, prices, lifecycle, SKU, URL, category).
 *
 * One ProviderSpecific row per source distributor, indexed inside
 * AggregatedPartCandidate by adapter name.
 */
final readonly class ProviderSpecific
{
	public function __construct(
		public string                                                            $sourceSku,
		public ?string                                                           $productUrl,
		public ?\Limas\Service\Integration\InfoProvider\Enum\ManufacturingStatus $lifecycleStatus,
		public ?string                                                           $categoryName,
		public ?int                                                              $stock,
		public ?string                                                           $currency,
		/** @var PriceBreak[] */
		public array                                                             $priceBreaks
	)
	{
	}
}
