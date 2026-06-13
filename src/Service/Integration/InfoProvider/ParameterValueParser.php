<?php declare(strict_types=1);

namespace Limas\Service\Integration\InfoProvider;

use Limas\Service\Integration\InfoProvider\Dto\Parameter;


/**
 * Stage-2 parameter post-processing for the aggregator pipeline
 *
 * Stage 1 (ParameterNormalizer) maps vendor `rawName` → canonical name via
 * the ParameterAlias table. Stage 2 (this) parses `rawValue` into
 * structured numeric fields + unit + SI-prefix and lifts `(Max)/(Min)/(Typ)`
 * suffixes off the canonical name onto a separate `qualifier` field.
 *
 * Why split: the frontend's "Apply Data" flow writes back to PartParameter
 * which has first-class columns for value/minValue/maxValue/unit/siPrefix.
 * Without this stage, every aggregator-imported parameter lands in
 * `stringValue` only — searches/filters on numeric ranges then don't work.
 *
 * Regex strategy (ported in spirit from Part-DB's ParameterDTO::parseValueField):
 *  1. Strip surrounding whitespace.
 *  2. Detect range markers — `…`, `...`, `~`, `to` — split into min/max.
 *  3. Detect symmetric range — `±N` or `+/-N` → ±N → min/max.
 *  4. Detect `value@text` (e.g. "2.5 @ 25°C") — split numeric vs trailing
 *     context, store trailing string in `valueText`.
 *  5. For each numeric token, splitIntoValueAndUnit() — match
 *     `(-?\d+(?:\.\d+)?)\s*([SI-prefix?][unit-symbol]?)` greedy from end.
 *
 * Limits we accept:
 *  - We do NOT normalize "10kΩ" → 10000 Ω. We keep `numericValue=10`,
 *    `siPrefix=k`, `unit=Ω` so the frontend's existing Unit+SiPrefix store
 *    lookup pattern (mirrors Octopart's DataApplicator) can drop in
 *    unchanged. PartParameter.value stores the un-prefixed coefficient.
 *  - We do NOT handle compound units like "V/µs" beyond stuffing them in
 *    `unit` as a literal symbol — they won't match a Unit entity and the
 *    frontend will leave Unit empty. Numeric value still lands correctly.
 *  - Hex / binary / scientific notation (`1e-6`) not supported. Vendors
 *    almost never emit those for parameters; if they do we keep the raw
 *    string in `stringValue` at apply time and skip the numeric route.
 */
final class ParameterValueParser
{
	/**
	 * SI prefix symbols recognised at the start of a unit token. Order matters:
	 * `da` (deca, 2 chars) and `Ki`/`Mi`/… (binary, 2 chars) MUST be tried
	 * before the single-char prefixes so the longer match wins. Symbols
	 * come from the seeded `SiPrefix` table — keep in sync if that changes.
	 *
	 * The 'μ' (greek mu, U+03BC) and the visually identical 'µ' (micro sign,
	 * U+00B5) both occur in distributor data; both map to micro.
	 */
	private const array SI_PREFIXES_LONG = ['da', 'Ki', 'Mi', 'Gi', 'Ti', 'Pi'];
	private const array SI_PREFIXES_SHORT = [
		'Q', 'R', 'Y', 'Z', 'E', 'P', 'T', 'G', 'M', 'k', 'h',
		'd', 'c', 'm', 'μ', 'µ', 'n', 'p', 'f', 'a', 'z', 'y', 'r', 'q',
	];
	/**
	 * Unit symbols (longest first so multi-char like "°C" / "Ah" wins over
	 * accidental partial matches). Includes "%" which isn't seeded as a
	 * Unit entity but is a near-universal parameter unit; the frontend's
	 * unit-store lookup will drop it on apply time and only the numeric
	 * value will land — that's the desired behaviour for tolerances.
	 */
	private const array UNIT_SYMBOLS = [
		'°C', '°F', 'mol', 'cd', 'Ah', 'lm', 'lx', 'Bq', 'Gy', 'Sv', 'kat',
		'Wb', 'Hz', 'Pa', 'rad', 'sr', 'eV',
		'Ω', 'm', 'g', 's', 'K', 'A', 'V', 'N', 'J', 'W', 'C', 'F', 'S',
		'T', 'H', '%',
	];
	/**
	 * Tail markers on canonical names that indicate which of value/min/max
	 * the parsed number should land in. Case-insensitive. Order matters
	 * for nested suffixes like "Max." (with dot) — we try the most
	 * decorated first.
	 *
	 * Note: ParameterNormalizer's SUFFIX_NOISE intentionally does NOT strip
	 * these; this is where they get consumed instead.
	 */
	private const array QUALIFIER_PATTERNS = [
		// Separator before the qualifier token is one of:
		//   - "(...)" parenthesised: "Operating Temperature (Max)"
		//   - "," or "-" with whitespace: "Operating Temperature, Min"
		//   - bare whitespace: "Forward Voltage Typ."
		// Anchored at end-of-string so words like "Maximum Operating
		// Temperature" (qualifier at START) do NOT match.
		'max' => '/^(?<name>.+?)\s*(?:\(\s*(?:max|maximum)\.?\s*\)|[,\-]\s*(?:max|maximum)\.?|\s+(?:max|maximum)\.?)\s*$/iu',
		'min' => '/^(?<name>.+?)\s*(?:\(\s*(?:min|minimum)\.?\s*\)|[,\-]\s*(?:min|minimum)\.?|\s+(?:min|minimum)\.?)\s*$/iu',
		'typ' => '/^(?<name>.+?)\s*(?:\(\s*(?:typ|typical)\.?\s*\)|[,\-]\s*(?:typ|typical)\.?|\s+(?:typ|typical)\.?)\s*$/iu',
	];


	/**
	 * Populate `numericValue` / `numericMin` / `numericMax` / `unit` /
	 * `siPrefix` / `qualifier` / `valueText` on the Parameter, mutating
	 * in place. Idempotent — re-running over a parsed parameter is a no-op
	 * because the new write reproduces the same fields.
	 */
	public function parse(Parameter $p): void
	{
		// Qualifier lives on the NAME, not the value. We extract from
		// canonicalName (Stage 1's output) when present so the qualifier
		// rides on top of the canonicalisation; fall back to rawName for
		// parameters that skipped Stage 1
		$nameSource = $p->canonicalName ?? $p->rawName;
		foreach (self::QUALIFIER_PATTERNS as $qual => $pattern) {
			if (preg_match($pattern, $nameSource, $m) === 1) {
				$p->qualifier = $qual;
				$stripped = trim($m['name']);
				if ($stripped !== '') {
					// Only overwrite canonicalName if Stage 1 actually
					// produced one; otherwise leave canonicalName as-is
					// (= the original null) so the existing fallback
					// `canonicalName ?? rawName` keeps working.
					if ($p->canonicalName !== null) {
						$p->canonicalName = $stripped;
					}
				}
				break;
			}
		}

		// Split off `@trailing` context (e.g. "2.5 V @ 25°C"). The trailing
		// segment stays in `valueText`; the numeric extraction continues
		// on the head.
		$value = trim($p->rawValue);
		if ($value === '') {
			return;
		}
		if (preg_match('/^(?<head>.+?)\s*@\s*(?<tail>.+)$/u', $value, $m) === 1) {
			$value = trim($m['head']);
			$p->valueText = trim($m['tail']);
		}
		// Split off trailing parenthesised context (e.g. "85°C (TA)" → "85°C"
		// keeping "(TA)" in valueText). Reference-condition notes like (TA),
		// (min), (typ) at the END of a value otherwise glom onto the unit
		// symbol and break the frontend's Unit-store lookup. Multiple paren
		// groups are concatenated.
		if (preg_match('/^(?<head>.+?)\s*(\(.+\))\s*$/u', $value, $m) === 1) {
			$value = trim($m['head']);
			$paren = trim($m[2], '() ');
			$p->valueText = $p->valueText !== null ? $p->valueText . ' / ' . $paren : $paren;
		}

		// Range patterns — try longest separators first ("..." before ".", "to" with surrounding whitespace before raw chars)
		$rangeSep = '(?:\.{2,}|…|~|\s+to\s+)';
		if (preg_match('#^(?<min>-?\d+(?:[.,]\d+)?)\s*[^0-9-]*\s*' . $rangeSep . '\s*(?<max>-?\d+(?:[.,]\d+)?)\s*(?<rest>.*)$#u', $value, $m) === 1) {
			$p->numericMin = $this->toFloat($m['min']);
			$p->numericMax = $this->toFloat($m['max']);
			$this->splitUnit(trim($m['rest']), $p);
			return;
		}

		// Symmetric ± — "±5%" → min=-5, max=5, unit="%"
		if (preg_match('/^[±]\s*(?<n>\d+(?:[.,]\d+)?)\s*(?<rest>.*)$/u', $value, $m) === 1
			|| preg_match('/^\+\/-\s*(?<n>\d+(?:[.,]\d+)?)\s*(?<rest>.*)$/u', $value, $m) === 1
		) {
			$n = $this->toFloat($m['n']);
			$p->numericMin = -$n;
			$p->numericMax = $n;
			$this->splitUnit(trim($m['rest']), $p);
			return;
		}

		// Plain single value
		if (preg_match('/^(?<n>-?\d+(?:[.,]\d+)?)\s*(?<rest>.*)$/u', $value, $m) === 1) {
			$p->numericValue = $this->toFloat($m['n']);
			$this->splitUnit(trim($m['rest']), $p);
			return;
		}

		// Pure text — nothing to parse. Leave numeric fields null;
		// frontend will fall back to stringValue at apply time.
	}

	/**
	 * Parse a residual token after the number — typical shape is
	 * `<si-prefix><unit-symbol>` with no whitespace ("nF", "kΩ") or one
	 * whitespace ("100 nF") or a multi-char unit standalone ("°C", "%").
	 * Sets `siPrefix` + `unit` on the parameter; either may stay null.
	 */
	private function splitUnit(string $token, Parameter $p): void
	{
		if ($token === '') {
			return;
		}
		// Match a known multi-char prefix first (binary or `da`) before
		// short single-char prefixes — `da` shadows `d`+ trailing 'a' etc.
		foreach (self::SI_PREFIXES_LONG as $prefix) {
			if (str_starts_with($token, $prefix)) {
				$rest = substr($token, strlen($prefix));
				if ($rest === '' || $this->matchUnit($rest, $p)) {
					$p->siPrefix = $prefix;
					return;
				}
			}
		}
		// Try a short prefix only if the remainder ALSO matches a known
		// unit symbol — otherwise the leading char is probably the unit
		// itself (e.g. "%" or "K" without prefix)
		foreach (self::SI_PREFIXES_SHORT as $prefix) {
			if (mb_substr($token, 0, mb_strlen($prefix)) !== $prefix) {
				continue;
			}
			$rest = mb_substr($token, mb_strlen($prefix));
			if ($rest === '') {
				continue;
			}
			if ($this->matchUnit($rest, $p)) {
				$p->siPrefix = $prefix === 'µ' ? 'μ' : $prefix; // normalise micro to greek mu
				return;
			}
		}
		// No prefix — token IS the unit (or unknown)
		$this->matchUnit($token, $p);
	}

	/**
	 * Match `$token` against known unit symbols. On hit, set
	 * `$p->unit` and return true. Strict equality — anything trailing
	 * (e.g. "Ω·m") is silently kept as-is so the frontend's store lookup
	 * just won't find it. Returns false on miss so the caller can decide
	 * whether to keep walking the prefix loop.
	 */
	private function matchUnit(string $token, Parameter $p): bool
	{
		foreach (self::UNIT_SYMBOLS as $symbol) {
			if ($token === $symbol) {
				$p->unit = $symbol;
				return true;
			}
		}
		// Best-effort fallback: keep the raw symbol even if it didn't
		// match the seeded list — saves "Ω·m" / "V/µs" verbatim so the
		// raw display still has a unit string in the frontend.
		$p->unit = $token;
		return false;
	}

	private function toFloat(string $numeric): float
	{
		return (float)str_replace(',', '.', $numeric);
	}
}
