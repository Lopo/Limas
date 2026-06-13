<?php

namespace Limas\Service\Integration\InfoProvider\Dto;


/**
 * Result of merging per-distributor InfoProviderResult rows that point to
 * the same physical part (same manufacturer + MPN)
 *
 * Top-level identification fields (manufacturer, MPN, description, datasheet,
 * imageUrl, packageName) are merged across sources with provenance and
 * conflict flags. Provider-specific fields (stock, prices, lifecycle,
 * SKU, productUrl, category) remain per-source in `providerSpecific`.
 *
 * `parameters` is currently a flat union of all parameter rows from every
 * source — the normalisation/canonical-name layer is a separate follow-up.
 */
final class AggregatedPartCandidate
{
	/**
	 * Filled by `InfoProviderAggregator` AFTER merging when the
	 * `ExistingPartFinder` matched a local Part on canonical (manufacturer,
	 * MPN). Frontend uses this to flag "already in your inventory" so the
	 * user doesn't accidentally add a duplicate. Stays null when no match.
	 */
	public ?ExistingPartInfo $existingPart = null;


	public function __construct(
		public readonly FieldWithProvenance $manufacturerName,
		public readonly FieldWithProvenance $manufacturerPartNumber,
		public readonly FieldWithProvenance $description,
		public readonly FieldWithProvenance $datasheetUrl,
		public readonly FieldWithProvenance $imageUrl,
		public readonly FieldWithProvenance $packageName,
		/** @var array<string, ProviderSpecific> distributor name => row */
		public readonly array               $providerSpecific,
		/** @var array<string, Parameter[]> distributor name => parameter list, raw (canonical names not yet mapped) */
		public readonly array               $parameters,
		/** @var string[] field names that ended up with isConflict=true */
		public readonly array               $conflicts,
		/** @var string[] adapter names that contributed to this candidate */
		public readonly array               $contributingSources
	)
	{
	}
}
