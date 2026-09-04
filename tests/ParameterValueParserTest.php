<?php

namespace Limas\Tests;

use Limas\Service\Integration\InfoProvider\Dto\Parameter;
use Limas\Service\Integration\InfoProvider\ParameterValueParser;
use PHPUnit\Framework\TestCase;


class ParameterValueParserTest
	extends TestCase
{
	private ParameterValueParser $parser;


	protected function setUp(): void
	{
		$this->parser = new ParameterValueParser;
	}

	private function parse(string $rawValue, string $rawName = 'Param', ?string $canonicalName = null): Parameter
	{
		$p = new Parameter($rawName, $rawValue, null, $canonicalName);
		$this->parser->parse($p);
		return $p;
	}

	public function testPlainValueSplitsPrefixAndUnit(): void
	{
		$p = $this->parse('10kΩ');
		self::assertSame(10.0, $p->numericValue);
		self::assertSame('k', $p->siPrefix);
		self::assertSame('Ω', $p->unit);
		self::assertNull($p->numericMin);

		$p = $this->parse('100 nF');
		self::assertSame(100.0, $p->numericValue);
		self::assertSame('n', $p->siPrefix);
		self::assertSame('F', $p->unit);
	}

	public function testSpelledOutUnitNamesResolveToSymbol(): void
	{
		// DigiKey writes resistance as "20 Ohms" — must map to the "Ω" Unit,
		// otherwise every imported resistor's Resistance lands unit-less
		$p = $this->parse('20 Ohms');
		self::assertSame(20.0, $p->numericValue);
		self::assertNull($p->siPrefix);
		self::assertSame('Ω', $p->unit);

		// Prefix + spelled-out unit ("4.7 kOhm" → 4.7 kΩ)
		$p = $this->parse('4.7 kOhm');
		self::assertSame(4.7, $p->numericValue);
		self::assertSame('k', $p->siPrefix);
		self::assertSame('Ω', $p->unit);

		$p = $this->parse('0.1 Watts');
		self::assertSame(0.1, $p->numericValue);
		self::assertSame('W', $p->unit);
	}

	public function testAsciiMicroPrefix(): void
	{
		// Distributors routinely write micro as ASCII "u" — "4.7uH", "10 uF".
		// Must normalise to the greek-mu SI prefix so it resolves to the seed.
		$p = $this->parse('4.7uH');
		self::assertSame(4.7, $p->numericValue);
		self::assertSame('μ', $p->siPrefix);
		self::assertSame('H', $p->unit);

		$p = $this->parse('10 uF');
		self::assertSame(10.0, $p->numericValue);
		self::assertSame('μ', $p->siPrefix);
		self::assertSame('F', $p->unit);
	}

	public function testUnitWithoutPrefix(): void
	{
		$p = $this->parse('5 V');
		self::assertSame(5.0, $p->numericValue);
		self::assertNull($p->siPrefix);
		self::assertSame('V', $p->unit);

		$p = $this->parse('5%');
		self::assertSame(5.0, $p->numericValue);
		self::assertNull($p->siPrefix);
		self::assertSame('%', $p->unit);
	}

	public function testBareNumber(): void
	{
		$p = $this->parse('42');
		self::assertSame(42.0, $p->numericValue);
		self::assertNull($p->unit);
		self::assertNull($p->siPrefix);
	}

	public function testCommaDecimalIsNormalised(): void
	{
		$p = $this->parse('2,5V');
		self::assertSame(2.5, $p->numericValue);
		self::assertSame('V', $p->unit);
	}

	public function testRangeSplitsMinMax(): void
	{
		$p = $this->parse('-40°C ~ 70°C');
		self::assertSame(-40.0, $p->numericMin);
		self::assertSame(70.0, $p->numericMax);
		self::assertSame('°C', $p->unit);
		self::assertNull($p->numericValue);
	}

	public function testRangeVariantsFromDistributors(): void
	{
		// Real LM358 operating-temperature spellings seen across DigiKey /
		// Farnell / LCSC / TME. The "+"-glued upper bound ("0°C~+70°C") used to
		// fall through to a bare string; now it decomposes like the others.
		foreach (['0°C~+70°C', '0°C ~ 70°C', '0...70°C', '0…70°C'] as $raw) {
			$p = $this->parse($raw);
			self::assertSame(0.0, $p->numericMin, $raw);
			self::assertSame(70.0, $p->numericMax, $raw);
			self::assertSame('°C', $p->unit, $raw);
			self::assertNull($p->numericValue, $raw);
		}
	}

	public function testLeadingPlusSingleValue(): void
	{
		$p = $this->parse('+70°C');
		self::assertSame(70.0, $p->numericValue);
		self::assertSame('°C', $p->unit);
		self::assertNull($p->numericMin);
	}

	public function testCjkFullwidthNormalisation(): void
	{
		// LCSC's real spelling: full-width tilde U+FF5E, ℃ U+2103, "+"-glued
		// upper bound. Must fold to ASCII and split min/max with a °C unit so it
		// groups with the western "0°C ~ 70°C" spellings instead of standing out.
		$p = $this->parse("0\u{2103}\u{FF5E}+70\u{2103}");
		self::assertSame(0.0, $p->numericMin);
		self::assertSame(70.0, $p->numericMax);
		self::assertSame('°C', $p->unit);
		self::assertNull($p->numericValue);

		// Full-width digits + ohm-sign (U+2126) fold to a plain number and the
		// canonical Greek omega the Unit store is keyed on
		$p = $this->parse("\u{FF11}\u{FF10}k\u{2126}");
		self::assertSame(10.0, $p->numericValue);
		self::assertSame('k', $p->siPrefix);
		self::assertSame("\u{03A9}", $p->unit);
	}

	public function testSymmetricRange(): void
	{
		$p = $this->parse('±5%');
		self::assertSame(-5.0, $p->numericMin);
		self::assertSame(5.0, $p->numericMax);
		self::assertSame('%', $p->unit);

		$p = $this->parse('+/-5%');
		self::assertSame(-5.0, $p->numericMin);
		self::assertSame(5.0, $p->numericMax);
	}

	public function testAtReferenceContextGoesToValueText(): void
	{
		$p = $this->parse('2.5 V @ 25°C');
		self::assertSame(2.5, $p->numericValue);
		self::assertSame('V', $p->unit);
		self::assertSame('25°C', $p->valueText);
	}

	public function testParenthesisedContextGoesToValueText(): void
	{
		$p = $this->parse('85°C (TA)');
		self::assertSame(85.0, $p->numericValue);
		self::assertSame('°C', $p->unit);
		self::assertSame('TA', $p->valueText);
	}

	public function testMicroSignNormalisedToGreekMu(): void
	{
		// micro sign U+00B5 must normalise to greek mu U+03BC
		$p = $this->parse("10\u{00B5}F");
		self::assertSame(10.0, $p->numericValue);
		self::assertSame("\u{03BC}", $p->siPrefix);
		self::assertSame('F', $p->unit);
	}

	public function testQualifierLiftedFromCanonicalName(): void
	{
		$p = $this->parse('125', 'Operating Temperature (Max)', 'Operating Temperature (Max)');
		self::assertSame('max', $p->qualifier);
		self::assertSame('Operating Temperature', $p->canonicalName);

		// qualifier read off rawName when Stage-1 produced no canonicalName
		$p = $this->parse('0.7', 'Forward Voltage Typ.');
		self::assertSame('typ', $p->qualifier);
	}

	public function testPureTextLeavesNumericNull(): void
	{
		$p = $this->parse('Yes');
		self::assertNull($p->numericValue);
		self::assertNull($p->numericMin);
		self::assertNull($p->numericMax);
	}

	/**
	 * Package/case codes with a significant leading zero must NOT be numeric-
	 * parsed — "0603" -> 603 would drop the zero. They stay string
	 * (numeric fields null; frontend renders stringValue).
	 */
	public function testLeadingZeroPackageCodesStayString(): void
	{
		foreach (['0603', '0805', '0402', '0201', '0603 (1608 Metric)', '01005'] as $code) {
			$p = $this->parse($code, 'Case/Package');
			self::assertNull($p->numericValue, "'$code' must not become a number");
			self::assertNull($p->numericMin, "'$code' must not become a range");
			self::assertNull($p->numericMax, "'$code' must not become a range");
		}
	}

	/**
	 * The leading-zero guard must not swallow real measurements: a bare zero,
	 * a decimal below one, and non-leading-zero codes still parse numerically
	 */
	public function testLeadingZeroGuardDoesNotBreakRealValues(): void
	{
		self::assertSame(0.0, $this->parse('0')->numericValue);
		self::assertSame(0.0, $this->parse('0 Ω')->numericValue);
		self::assertSame(0.5, $this->parse('0.5')->numericValue);
		self::assertSame(0.1, $this->parse('0.1 W')->numericValue);
		self::assertSame(1206.0, $this->parse('1206')->numericValue);
	}
}
