<?php

namespace Limas\Service\Integration\InfoProvider\Contract;


/**
 * Standalone capability: parse a distributor product-detail URL into
 * `{mpn, manufacturer}` so the aggregator can preflight a paste-URL
 * shortcut for the user.
 *
 * Decoupled from `InfoProviderInterface` so distributors WITHOUT a
 * working data adapter (e.g. Mouser while their API key is dead) can
 * still contribute URL parsing — the aggregator search then runs
 * via the other configured adapters with the lifted MPN.
 *
 * Implementations MUST be regex-only (no HTTP fetch). When the URL
 * shape doesn't match, return null — the caller will try the next
 * handler, then fall back to surfacing an error.
 */
interface URLHandlerInterface
{
	/**
	 * Identifier used in logging + result payloads ('digikey', 'mouser',
	 * 'farnell', …). Adapters that also implement InfoProviderInterface
	 * typically return the same string here.
	 */
	public function getName(): string;

	/**
	 * Lower-case host names this handler is willing to parse. Matched
	 * with str_ends_with on the parsed URL host, so regional aliases
	 * (sk.farnell.com, uk.farnell.com, …) collapse to a single entry
	 * like `farnell.com`. Empty array disables URL handling.
	 *
	 * @return string[]
	 */
	public function getHandledDomains(): array;

	/**
	 * Try to lift `{manufacturer, mpn}` (and optionally a distributor-
	 * specific `sourceSku`) out of the URL. Query string + fragment are
	 * stripped before the call — implementations see only the canonical
	 * scheme://host/path form.
	 *
	 * `sourceSku` should be set when the URL carries the distributor's
	 * own unique part identifier (DigiKey part number, Farnell /dp/N,
	 * LCSC Cxxxxx, …). The aggregator uses it for tighter auto-pick:
	 * matching against `providerSpecific[source].sourceSku` survives
	 * package-suffix ambiguity that string-matching mfr+mpn doesn't.
	 *
	 * @return array{mpn: string, manufacturer: string, sourceSku?: string}|null
	 */
	public function tryExtractFromURL(string $url): ?array;
}
