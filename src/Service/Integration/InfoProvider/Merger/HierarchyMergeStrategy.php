<?php

namespace Limas\Service\Integration\InfoProvider\Merger;

use Limas\Service\Integration\InfoProvider\Dto\FieldWithProvenance;


/**
 * Trust-order strategy: picks the first distributor in `priority` order that
 * reported a non-empty value. Suitable when you have a clear "this distributor
 * tends to have the cleanest data" preference (typical for 2-source scenarios
 * where majority voting cannot apply).
 *
 * Conflict flag is set if 2+ distributors reported different non-empty values.
 */
final readonly class HierarchyMergeStrategy
	implements MergeStrategyInterface
{
	/**
	 * @param string[] $priority distributor names from most trusted to least, e.g. ['digikey', 'mouser', 'farnell', 'tme']
	 */
	public function __construct(
		private array $priority
	)
	{
	}

	public function resolve(array $values): FieldWithProvenance
	{
		$nonEmpty = array_filter($values, static fn(?string $v): bool => $v !== null && $v !== '');

		if ($nonEmpty === []) {
			return new FieldWithProvenance(null, $values, false, FieldWithProvenance::RESOLUTION_ONLY_SOURCE);
		}

		// Normalised distinctness — case+whitespace-only differences are not
		// real conflicts. See MajorityMergeStrategy::softNormalize for the
		// rationale; both strategies share the same normalisation rule.
		$normalizedDistinct = array_unique(array_map(
			static fn(string $v): string => MajorityMergeStrategy::softNormalize($v),
			array_values($nonEmpty)
		));
		$isConflict = count($normalizedDistinct) > 1;

		// Pick by priority order
		$chosen = null;
		foreach ($this->priority as $src) {
			if (array_key_exists($src, $nonEmpty)) {
				$chosen = $nonEmpty[$src];
				break;
			}
		}
		// Fallback: any non-empty value (when no priority source contributed)
		if ($chosen === null) {
			$chosen = reset($nonEmpty);
		}

		if ($isConflict) {
			$resolution = FieldWithProvenance::RESOLUTION_HIERARCHY;
		} elseif (count($nonEmpty) === 1) {
			$resolution = FieldWithProvenance::RESOLUTION_ONLY_SOURCE;
		} else {
			$resolution = FieldWithProvenance::RESOLUTION_CONSENSUS;
		}

		return new FieldWithProvenance($chosen, $values, $isConflict, $resolution);
	}
}
