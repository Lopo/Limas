<?php

namespace Limas\Tests;

use Limas\Entity\PartParameter;
use Limas\Entity\SiPrefix;
use PHPUnit\Framework\TestCase;


/**
 * Regression guard for an HTTP 500 when saving a Part: a PartParameter can carry
 * an SI prefix on a slot whose value is null — a range-only spec like "180 mm"
 * lands as maxValue=180 with value=null, yet the aggregator still attached a
 * prefix to the empty `value` slot. recalculateNormalizedValues() then called
 * SiPrefix::calculateProduct(null), which type-errors (it takes a float).
 */
class PartParameterNormalizationTest
	extends TestCase
{
	/**
	 * Prefix on a null value slot must normalize to null, not crash. This is
	 * the exact shape that 500'd (part LQM2HPN2R2MG0L, "Roll diameter").
	 */
	public function testSiPrefixOnNullValueDoesNotCrash(): void
	{
		$milli = new SiPrefix('milli', 'm', -3, 10);

		$p = new PartParameter;
		$p->setMaxValue(180.0); // range-only: value stays null
		$p->setSiPrefix($milli); // prefix on the (null) value slot — used to throw here

		self::assertNull($p->getNormalizedValue(), 'null value normalizes to null regardless of prefix');
		self::assertSame(180.0, $p->getNormalizedMaxValue(), 'maxValue keeps its value (no prefix on the max slot)');
	}

	/**
	 * The prefix must still scale a non-null value — the guard only short-circuits null
	 */
	public function testSiPrefixStillScalesNonNullValue(): void
	{
		$micro = new SiPrefix('micro', 'u', -6, 10);

		$p = new PartParameter;
		$p->setSiPrefix($micro);
		$p->setValue(2.2); // 2.2 µH

		self::assertEqualsWithDelta(2.2e-6, $p->getNormalizedValue(), 1e-18);
	}
}
