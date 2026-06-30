<?php

namespace Limas\Tests;

use Limas\Service\Integration\InfoProvider\Merger\SimilarityCollapser;
use PHPUnit\Framework\TestCase;


class SimilarityCollapserTest
	extends TestCase
{
	private SimilarityCollapser $collapser;


	protected function setUp(): void
	{
		$this->collapser = new SimilarityCollapser;
	}

	public function testCollapsePackageTokenSetOverlap(): void
	{
		// TME "TO92" and Farnell "TO-92" normalise to the same token set
		$out = $this->collapser->collapsePackages([
			'tme' => 'TO92',
			'farnell' => 'TO-92'
		]);
		// Both should rewrite to the same representative (shortest raw)
		self::assertSame($out['tme'], $out['farnell']);
		self::assertSame('TO92', $out['tme']);
	}

	public function testCollapsePackagePrefixMatchAcrossCommaList(): void
	{
		// DigiKey returns multiple aliases comma-separated; "TO-92-3" should prefix-match "TO92" from TME / Farnell
		$out = $this->collapser->collapsePackages([
			'tme' => 'TO92',
			'farnell' => 'TO-92',
			'digikey' => 'TO-226-3, TO-92-3 (TO-226AA)'
		]);
		// All three end up with the same representative
		self::assertSame($out['tme'], $out['farnell']);
		self::assertSame($out['tme'], $out['digikey']);
		// Representative is the shortest raw (TO92 here)
		self::assertSame('TO92', $out['tme']);
	}

	public function testCollapsePackageDoesNotMatchOnTooShortPrefix(): void
	{
		// Short prefixes shouldn't bridge clusters: "SOT23" vs "SOT223" must NOT collapse — they're genuinely different packages
		$out = $this->collapser->collapsePackages([
			'a' => 'SOT23',
			'b' => 'SOT223'
		]);
		self::assertNotSame($out['a'], $out['b']);
	}

	public function testCollapsePackageHandlesNullAndEmpty(): void
	{
		$out = $this->collapser->collapsePackages([
			'a' => null,
			'b' => '',
			'c' => 'TO92'
		]);
		// Non-eligible sources stay untouched
		self::assertNull($out['a']);
		self::assertSame('', $out['b']);
		self::assertSame('TO92', $out['c']);
	}

	public function testCollapseDescriptionsClusterAcrossSimilarText(): void
	{
		$out = $this->collapser->collapseDescriptions([
			'digikey' => 'Bipolar (BJT) Transistor PNP 40 V 200 mA 250MHz 625 mW Through Hole TO-92',
			'tme' => 'Bipolar transistor, PNP, 40V, 200mA, TO-92',
			'farnell' => 'PNP transistor 40V 200mA TO-92'
		]);
		// All three are about the same part — should collapse to one rep
		self::assertSame($out['digikey'], $out['tme']);
		self::assertSame($out['digikey'], $out['farnell']);
		// Representative is the longest (most informative) raw text
		self::assertSame(
			'Bipolar (BJT) Transistor PNP 40 V 200 mA 250MHz 625 mW Through Hole TO-92',
			$out['digikey']
		);
	}

	public function testCollapseDescriptionsKeepsTrulyDifferentSeparate(): void
	{
		$out = $this->collapser->collapseDescriptions([
			'a' => 'Bipolar PNP transistor 40V 200mA TO-92',
			'b' => 'Voltage regulator 5V 1A TO-220'
		]);
		// Different parts, no token overlap above threshold — stay distinct
		self::assertSame('Bipolar PNP transistor 40V 200mA TO-92', $out['a']);
		self::assertSame('Voltage regulator 5V 1A TO-220', $out['b']);
		self::assertNotSame($out['a'], $out['b']);
	}

	public function testCollapseDescriptionsThresholdRespected(): void
	{
		$strict = new SimilarityCollapser(0.95);
		$lenient = new SimilarityCollapser(0.3);
		$values = [
			'a' => 'Bipolar transistor 40V 200mA',
			'b' => 'Bipolar transistor 60V 500mA'
		];
		// Strict threshold keeps them separate
		$strictOut = $strict->collapseDescriptions($values);
		self::assertNotSame($strictOut['a'], $strictOut['b']);
		// Lenient threshold collapses
		$lenientOut = $lenient->collapseDescriptions($values);
		self::assertSame($lenientOut['a'], $lenientOut['b']);
	}

	public function testCollapseDoesNothingForSingleSource(): void
	{
		// 1-source maps shouldn't get rewritten; nothing to cluster against
		$out = $this->collapser->collapsePackages(['tme' => 'TO92']);
		self::assertSame(['tme' => 'TO92'], $out);
	}

	public function testCollapseTransitiveClustering(): void
	{
		// A↔B match, B↔C match, but A↔C might not match directly. Cluster all three transitively
		$out = $this->collapser->collapsePackages([
			'a' => 'SOIC-8',
			'b' => 'SOIC8',
			'c' => 'SOIC8N'
		]);
		// "soic8" == "soic8" → a∼b; "soic8" prefix of "soic8n" → b∼c
		self::assertSame($out['a'], $out['b']);
		self::assertSame($out['b'], $out['c']);
	}
}
