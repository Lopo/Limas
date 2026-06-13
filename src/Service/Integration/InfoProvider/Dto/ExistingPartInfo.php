<?php

namespace Limas\Service\Integration\InfoProvider\Dto;


/**
 * Light projection of a local Part that already matches a merged candidate.
 * Carried inside `AggregatedPartCandidate` so the search UI can flag
 * "Already in inventory" without a second round-trip when the user clicks.
 */
final readonly class ExistingPartInfo
{
	public function __construct(
		public int     $partId,
		public ?string $partName,
		public ?string $partDescription,
		public ?string $storageLocationName,
		public ?int    $totalStock
	)
	{
	}
}
