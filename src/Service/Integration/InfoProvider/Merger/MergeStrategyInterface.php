<?php

namespace Limas\Service\Integration\InfoProvider\Merger;

use Limas\Service\Integration\InfoProvider\Dto\FieldWithProvenance;


/**
 * Strategy for resolving a single field across multiple distributors that disagree on its value
 */
interface MergeStrategyInterface
{
	/**
	 * @param array<string, ?string> $values distributor name => value (null/empty means missing)
	 * @return FieldWithProvenance describing chosenValue + how it was picked + isConflict
	 */
	public function resolve(array $values): FieldWithProvenance;
}
