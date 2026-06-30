<?php

namespace Limas\Service\Integration\InfoProvider\Merger;

use Limas\Service\FootprintCanonicalizer;


/**
 * Pre-strategy collapse step: rewrites the per-source value map so that
 * equivalent values across distributors share the same representative.
 * Strategies then count them as one vote.
 *
 * Without this, MajorityMergeStrategy treats "TO92" / "TO-92" / "TO-92-3"
 * as three distinct values even though they describe the same physical
 * package, and a 2-source agreement gets out-voted by a single source whose
 * string happens to be longer / parenthesised. Same shape problem on
 * descriptions where two distributors write similar prose and the third
 * uses a wildly different wording.
 *
 * Per-field rules:
 *
 * - **packageName** — split on `,/;()` then FootprintCanonicalizer::normalize
 *   each token; values share a cluster if their token sets intersect OR a
 *   token of one is a prefix of a token of the other (min 3 chars to keep
 *   "TO-92" ⊂ "TO-92-3" but not "X" ⊂ "XYZ"). Representative = shortest raw
 *   value in the cluster (most canonical).
 *
 * - **description** — lowercase + tokenise on whitespace + punctuation;
 *   Jaccard similarity ≥ threshold (default 0.5) means same cluster.
 *   Transitive — chain of pairwise-similar values cluster together.
 *   Representative = longest raw value (most informative).
 */
final readonly class SimilarityCollapser
{
	public function __construct(
		private float $descriptionJaccardThreshold = 0.5
	)
	{
	}

	/**
	 * @param array<string, ?string> $values
	 * @return array<string, ?string>
	 */
	public function collapsePackages(array $values): array
	{
		$nonEmpty = array_filter($values, static fn(?string $v): bool => $v !== null && $v !== '');
		if (count($nonEmpty) < 2) {
			return $values;
		}

		// Per-source token sets (each value tokenised on punctuation, every token normalised)
		$tokensBySource = array_map(fn(string $v): array => $this->tokenizePackage($v), $nonEmpty);

		$clusters = $this->cluster(
			array_keys($tokensBySource),
			function (string $a, string $b) use ($tokensBySource): bool {
				return $this->packageTokensMatch($tokensBySource[$a], $tokensBySource[$b]);
			}
		);

		return $this->rewriteByClusters($values, $clusters, function (array $cluster) use ($values): string {
			// Shortest raw — typical canonical form
			usort($cluster, static fn(string $a, string $b) => strlen((string)$values[$a]) <=> strlen((string)$values[$b]));
			return (string)$values[$cluster[0]];
		});
	}

	/**
	 * @param array<string, ?string> $values
	 * @return array<string, ?string>
	 */
	public function collapseDescriptions(array $values): array
	{
		$nonEmpty = array_filter($values, static fn(?string $v): bool => $v !== null && $v !== '');
		if (count($nonEmpty) < 2) {
			return $values;
		}

		$tokensBySource = array_map(fn(string $v): array => $this->tokenizeText($v), $nonEmpty);

		$threshold = $this->descriptionJaccardThreshold;
		$clusters = $this->cluster(
			array_keys($tokensBySource),
			function (string $a, string $b) use ($tokensBySource, $threshold): bool {
				return $this->jaccard($tokensBySource[$a], $tokensBySource[$b]) >= $threshold;
			}
		);

		return $this->rewriteByClusters($values, $clusters, function (array $cluster) use ($values): string {
			// Longest raw — most informative description
			usort($cluster, static fn(string $a, string $b) => strlen((string)$values[$b]) <=> strlen((string)$values[$a]));
			return (string)$values[$cluster[0]];
		});
	}

	/**
	 * Generic transitive-closure clusterer. `$pairMatches($a, $b)` returns true
	 * when the two keys should land in the same cluster. Runs O(N²) which is
	 * fine for the merger's at-most-~10 sources per group.
	 *
	 * @param string[] $keys
	 * @param callable(string, string): bool $pairMatches
	 * @return array<int, array<int, string>>
	 */
	private function cluster(array $keys, callable $pairMatches): array
	{
		$clusters = [];
		foreach ($keys as $key) {
			$mergedInto = null;
			foreach ($clusters as $idx => $cluster) {
				foreach ($cluster as $member) {
					if ($pairMatches($key, $member)) {
						$mergedInto = $idx;
						break 2;
					}
				}
			}
			if ($mergedInto === null) {
				$clusters[] = [$key];
				continue;
			}
			$clusters[$mergedInto][] = $key;

			// New member can bridge previously-disjoint clusters — fold them
			foreach ($clusters as $idx => $cluster) {
				if ($idx === $mergedInto) {
					continue;
				}
				foreach ($cluster as $member) {
					if ($pairMatches($key, $member)) {
						$clusters[$mergedInto] = array_merge($clusters[$mergedInto], $cluster);
						unset($clusters[$idx]);
						break;
					}
				}
			}
		}
		return array_values($clusters);
	}

	/**
	 * @param array<string, ?string> $values
	 * @param array<int, array<int, string>> $clusters
	 * @param callable(array<int, string>): string $pickRepresentative
	 * @return array<string, ?string>
	 */
	private function rewriteByClusters(array $values, array $clusters, callable $pickRepresentative): array
	{
		$out = $values;
		foreach ($clusters as $cluster) {
			if (count($cluster) < 2) {
				continue;
			}
			$repr = $pickRepresentative($cluster);
			foreach ($cluster as $src) {
				$out[$src] = $repr;
			}
		}
		return $out;
	}

	/**
	 * @return string[]
	 */
	private function tokenizePackage(string $raw): array
	{
		$parts = preg_split('/[,;\\/()]+/u', $raw);
		if ($parts === false) {
			$parts = [];
		}
		$tokens = [];
		foreach ($parts as $part) {
			$norm = FootprintCanonicalizer::normalize(trim($part));
			if ($norm !== '') {
				$tokens[$norm] = true;
			}
		}
		return array_keys($tokens);
	}

	/**
	 * Two package values "match" if their normalised token sets intersect OR
	 * any token of one is a prefix of any token of the other (min 3 chars to
	 * keep "to92" ⊂ "to923" but not "x" ⊂ "xyz")
	 *
	 * @param string[] $a
	 * @param string[] $b
	 */
	private function packageTokensMatch(array $a, array $b): bool
	{
		if (array_intersect($a, $b) !== []) {
			return true;
		}
		foreach ($a as $ta) {
			if (strlen($ta) < 3) {
				continue;
			}
			foreach ($b as $tb) {
				if (strlen($tb) < 3) {
					continue;
				}
				if (str_starts_with($tb, $ta) || str_starts_with($ta, $tb)) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * @return string[]
	 */
	private function tokenizeText(string $raw): array
	{
		$lower = mb_strtolower($raw);
		// Glue number+unit pairs that distributors often write spaced — "40 V"
		// and "40V" should produce the same token so Jaccard sees them as
		// the same value, not two distinct ones
		$glued = preg_replace('/(\\d)\\s+([a-z])/u', '$1$2', $lower);
		if ($glued === null) {
			$glued = $lower;
		}
		$parts = preg_split('/[\\s,.;!?:(){}\\[\\]<>"\\\'\\/\\\\]+/u', $glued, -1, PREG_SPLIT_NO_EMPTY);
		if ($parts === false) {
			return [];
		}
		return array_values(array_unique($parts));
	}

	/**
	 * @param string[] $a
	 * @param string[] $b
	 */
	private function jaccard(array $a, array $b): float
	{
		if ($a === [] && $b === []) {
			return 1.0;
		}
		$intersection = count(array_intersect($a, $b));
		$union = count(array_unique(array_merge($a, $b)));
		if ($union === 0) {
			return 0.0;
		}
		return $intersection / $union;
	}
}
