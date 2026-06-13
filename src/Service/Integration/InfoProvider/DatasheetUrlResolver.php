<?php

namespace Limas\Service\Integration\InfoProvider;

use Limas\Service\ManufacturerCanonicalizer;
use Psr\Log\LoggerInterface;


/**
 * Generates candidate datasheet URLs from manufacturer-direct patterns
 *
 * Used as a fallback by `AggregatorImporter` when no distributor reported
 * a datasheet URL for a candidate, or when the chosen distributor URL
 * failed to download. Manufacturer-direct URLs aren't an API — they're
 * the predictable PDF endpoints most major vendors host on their own
 * CDNs (TI, ST, Microchip, onsemi, NXP, …). No keys, no quotas.
 *
 * Patterns live in `data/datasheet-patterns.json`. Matching is done via
 * ManufacturerCanonicalizer's normalize() so "ON Semiconductor",
 * "ONSEMI", "onsemi" all resolve to the same pattern set.
 */
final class DatasheetUrlResolver
{
	/**
	 * @var array<int, array{normalizedNames: string[], urls: string[]}>
	 * Lazy-loaded from JSON on first call
	 */
	private ?array $compiledPatterns = null;


	public function __construct(
		private readonly string          $patternsFile,
		private readonly LoggerInterface $logger
	)
	{
	}

	/**
	 * Return candidate datasheet URLs for (manufacturer, MPN), in pattern
	 * order. Empty array when no pattern matches the manufacturer.
	 *
	 * Caller is expected to attempt downloads and pick the first one that
	 * yields a valid PDF — we don't HEAD-probe here because vendors often
	 * return 200 with an HTML "not found" page (bot detection), and the
	 * cheap-looking probe would just be a lie.
	 *
	 * @return string[]
	 */
	public function candidates(string $manufacturer, string $mpn): array
	{
		$normalized = ManufacturerCanonicalizer::normalize($manufacturer);
		if ($normalized === '' || $mpn === '') {
			return [];
		}

		$patterns = $this->getPatterns();
		$urls = [];
		foreach ($patterns as $entry) {
			if (!in_array($normalized, $entry['normalizedNames'], true)) {
				continue;
			}
			foreach ($entry['urls'] as $template) {
				$urls[] = strtr($template, [
					'{mpn}' => $mpn,
					'{mpn_lower}' => strtolower($mpn),
					'{mpn_upper}' => strtoupper($mpn)
				]);
			}
		}
		return $urls;
	}

	/**
	 * @return array<int, array{normalizedNames: string[], urls: string[]}>
	 */
	private function getPatterns(): array
	{
		if ($this->compiledPatterns !== null) {
			return $this->compiledPatterns;
		}

		if (!is_file($this->patternsFile)) {
			$this->logger->warning(sprintf('DatasheetUrlResolver: pattern file %s not found', $this->patternsFile));
			$this->compiledPatterns = [];
			return [];
		}

		$raw = file_get_contents($this->patternsFile);
		if ($raw === false) {
			$this->compiledPatterns = [];
			return [];
		}
		try {
			$decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
		} catch (\JsonException $e) {
			$this->logger->warning('DatasheetUrlResolver: pattern file parse error: ' . $e->getMessage());
			$this->compiledPatterns = [];
			return [];
		}

		$compiled = [];
		foreach (($decoded['patterns'] ?? []) as $entry) {
			if (!isset($entry['manufacturer'], $entry['urls']) || !is_array($entry['urls'])) {
				continue;
			}
			$names = [$entry['manufacturer']];
			foreach (($entry['aliases'] ?? []) as $alias) {
				$names[] = $alias;
			}
			$normalizedNames = array_values(array_unique(array_filter(
				array_map(
					static fn(string $n): string => ManufacturerCanonicalizer::normalize($n),
					$names
				),
				static fn(string $n): bool => $n !== ''
			)));
			$compiled[] = [
				'normalizedNames' => $normalizedNames,
				'urls' => array_values(array_filter($entry['urls'], 'is_string'))
			];
		}
		$this->compiledPatterns = $compiled;
		return $compiled;
	}
}
