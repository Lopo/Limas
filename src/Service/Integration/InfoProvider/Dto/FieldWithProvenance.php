<?php

namespace Limas\Service\Integration\InfoProvider\Dto;


/**
 * Holds the merged value of a single top-level field plus a record of what
 * each distributor reported. UI can use $isConflict to highlight cells where
 * sources disagreed and let the user override $chosenValue.
 */
final class FieldWithProvenance
{
	public const string RESOLUTION_ONLY_SOURCE = 'only_source'; // single distributor returned a value
	public const string RESOLUTION_CONSENSUS = 'consensus'; // all distributors agreed
	public const string RESOLUTION_MAJORITY = 'majority'; // 2+ sources agreed, others differed
	public const string RESOLUTION_HIERARCHY = 'hierarchy'; // tied / no majority — picked by trust order


	public function __construct(
		public readonly ?string $chosenValue,
		/** @var array<string, ?string> distributor name => what that distributor reported (null if missing) */
		public readonly array   $sourcesValues,
		public readonly bool    $isConflict,
		public readonly string  $resolution
	)
	{
	}
}
