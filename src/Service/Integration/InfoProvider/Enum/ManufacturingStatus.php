<?php

namespace Limas\Service\Integration\InfoProvider\Enum;


/**
 * Canonical part-lifecycle status. Replaces the free-form `lifecycleStatus`
 * string that adapters used to emit so the merger + frontend can reason
 * about state safely (color tags, sort order, filter). Per-adapter raw
 * strings get normalised via `fromRaw()` — the catch-all `Unknown` keeps
 * "vendor said something but we can't classify it" distinguishable from
 * "vendor said nothing" (which stays `null`).
 *
 * Ordering reflects severity for downstream UI (lower index = healthier):
 *  Active → PreRelease → NotRecommendedForNewDesigns → EndOfLife → Discontinued → Unknown
 */
enum ManufacturingStatus: string
{
	case Active = 'active';
	case PreRelease = 'pre_release';
	case NotRecommendedForNewDesigns = 'nrnd';
	case EndOfLife = 'eol';
	case Discontinued = 'discontinued';
	case Unknown = 'unknown';

	/**
	 * Map an adapter's raw status string to the canonical enum. Returns
	 * null when the input is blank (vendor didn't tell us anything);
	 * `Unknown` when the input is non-empty but doesn't match any known
	 * keyword (vendor said *something* we can't classify).
	 *
	 * Matching is case-insensitive substring on the lowercased input.
	 * Order is important: more specific phrases (e.g. "not recommended
	 * for new designs") must come before broader keywords ("active",
	 * "new") so the right case wins.
	 */
	public static function fromRaw(?string $raw): ?self
	{
		if ($raw === null) {
			return null;
		}
		$normalized = strtolower(trim($raw));
		if ($normalized === '' || $normalized === '-') {
			return null;
		}

		// Discontinued / obsolete: hard end state
		if (str_contains($normalized, 'discontinued')
			|| str_contains($normalized, 'obsolete')
			|| str_contains($normalized, 'archived')
			|| str_contains($normalized, 'no longer manufactured')
		) {
			return self::Discontinued;
		}

		// EOL: still buyable for a while, then gone
		if (str_contains($normalized, 'end of life')
			|| str_contains($normalized, 'end-of-life')
			|| preg_match('/\beol\b/', $normalized) === 1
			|| str_contains($normalized, 'last time buy')
			|| str_contains($normalized, 'last-time buy')
			|| str_contains($normalized, 'ltb')
		) {
			return self::EndOfLife;
		}

		// NRND family: still produced but vendors discourage new designs
		if (preg_match('/\bnrnd\b/', $normalized) === 1
			|| str_contains($normalized, 'not recommended')
			|| str_contains($normalized, 'not for new')
		) {
			return self::NotRecommendedForNewDesigns;
		}

		// PreRelease: announced but not yet (or just-barely) shipping
		if (str_contains($normalized, 'pre-release')
			|| str_contains($normalized, 'pre release')
			|| str_contains($normalized, 'prerelease')
			|| str_contains($normalized, 'pre-sale')
			|| str_contains($normalized, 'pre sale')
			|| str_contains($normalized, 'presale')
			|| str_contains($normalized, 'coming soon')
			|| str_contains($normalized, 'future')
			|| str_contains($normalized, 'announced')
		) {
			return self::PreRelease;
		}

		// Healthy production states
		if (str_contains($normalized, 'active')
			|| str_contains($normalized, 'production')
			|| str_contains($normalized, 'released')
			|| $normalized === 'new'
		) {
			return self::Active;
		}

		return self::Unknown;
	}

	/**
	 * Human-readable label for FE rendering. The enum's raw `value` is
	 * what the serializer ships over the wire; FE maps that to localised
	 * labels separately.
	 */
	public function label(): string
	{
		return match ($this) {
			self::Active => 'Active',
			self::PreRelease => 'Pre-release',
			self::NotRecommendedForNewDesigns => 'NRND',
			self::EndOfLife => 'EOL',
			self::Discontinued => 'Discontinued',
			self::Unknown => 'Unknown'
		};
	}
}
