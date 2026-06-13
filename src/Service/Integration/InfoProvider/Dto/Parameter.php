<?php

namespace Limas\Service\Integration\InfoProvider\Dto;


/**
 * A single specification parameter from a distributor
 *
 * `rawName` / `rawValue` are the source-of-truth — strings exactly as the
 * distributor returned them.
 *
 * Stage 1 (ParameterNormalizer): populates `canonicalName` from the
 * ParameterAlias lookup table.
 *
 * Stage 2 (ParameterValueParser): populates the numeric* + unit + siPrefix
 * + qualifier + valueText fields by running a regex stack over `rawValue`
 * and stripping `(Max)/(Min)/(Typ)` qualifiers from the canonical name.
 *
 * Both stages mutate in place so the aggregator can run them in a single
 * post-merge sweep without rebuilding the parameter chain.
 */
final class Parameter
{
	// Stage 1 — set by `ParameterNormalizer` in a post-merge pass
	public ?string $canonicalName = null;

	// Stage 2 — set by `ParameterValueParser`. All null until that runs.
	//
	// Either `numericValue` holds a single point ("100 nF" → 100), OR
	// `numericMin` + `numericMax` hold a range ("-40°C ~ 70°C" → -40..70),
	// OR `qualifier` says which of value/min/max the single number ends
	// up in once the aggregator collapses Min/Max/Typ canonical variants
	// into one PartParameter row.
	public ?float $numericValue = null;
	public ?float $numericMin = null;
	public ?float $numericMax = null;
	// Symbol-level — frontend resolves these to Unit / SiPrefix entities
	// via store lookup at apply time. Keeping symbols (not entity FKs)
	// here lets us serialize the DTO without an EM dependency.
	public ?string $unit = null;
	public ?string $siPrefix = null;
	// Name qualifier extracted from rawName — drives where the numeric
	// value lands when the frontend writes back PartParameter:
	//   'max' → maxValue, 'min' → minValue, 'typ'/null → value.
	public ?string $qualifier = null;
	// Anything left after the numeric extraction (e.g. "25°C" trailing a
	// `@` separator). Stored verbatim so we don't lose @reference-temperature
	// context like "2.5 V @ 25°C".
	public ?string $valueText = null;


	public function __construct(
		public readonly string  $rawName, // "Resistance" / "Capacitance" / "Operating Temperature (Max)"
		public readonly string  $rawValue, // "10kΩ" / "100 nF" / "-40°C ~ 70°C"
		public readonly ?string $rawUnit = null, // "V" / "F" — if the distributor returned the unit separately
		?string                 $canonicalName = null // Stage-1 result, optional pre-fill
	)
	{
		$this->canonicalName = $canonicalName;
	}
}
