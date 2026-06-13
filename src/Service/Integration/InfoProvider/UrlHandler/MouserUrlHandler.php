<?php

namespace Limas\Service\Integration\InfoProvider\UrlHandler;

use Limas\Service\Integration\InfoProvider\Contract\URLHandlerInterface;


/**
 * Standalone Mouser product-URL parser. We don't have a working Mouser
 * data adapter on this branch (their API key is dead pending support),
 * but Mouser URLs are common enough in the wild that supporting paste
 * is a quick win — the lifted MPN drives a normal aggregator search
 * across whichever distributors ARE configured (DigiKey, Farnell, …).
 *
 * Mouser URLs come in two stable shapes:
 *
 *   /ProductDetail/<vendor-code>-<mpn-with-extras>
 *     e.g. /ProductDetail/650-MINISMDC150F-16
 *     The leading 3-4 digit code is Mouser's internal supplier id
 *     (650 = Bourns, etc.). Everything after the first dash is the MPN.
 *
 *   /ProductDetail/<manufacturer>/<mpn>?qs=...
 *     e.g. /ProductDetail/onsemi/BC547BBU
 *     Two path segments after /ProductDetail/, mfr first then MPN.
 *
 * Both regional storefronts (mouser.com, mouser.sk, mouser.de, …)
 * use the same path conventions.
 */
final class MouserUrlHandler
	implements URLHandlerInterface
{
	public function getName(): string
	{
		return 'mouser';
	}

	/** @return string[] */
	public function getHandledDomains(): array
	{
		return ['mouser.com', 'mouser.sk', 'mouser.de', 'mouser.eu', 'eu.mouser.com'];
	}

	/**
	 * @return array{mpn: string, manufacturer: string}|null
	 */
	public function tryExtractFromURL(string $url): ?array
	{
		$path = parse_url($url, PHP_URL_PATH);
		if (!is_string($path)) {
			return null;
		}

		// `~` delimiter so the `#` inside the character classes below
		// doesn't collide with the regex delimiter (PHP would otherwise
		// terminate the pattern early and "]" would be parsed as an
		// unknown modifier).

		// Two-segment form: /ProductDetail/<mfr>/<mpn>
		if (preg_match('~^/ProductDetail/([^/]+)/([^/?#]+)/?$~i', $path, $m) === 1) {
			return [
				'manufacturer' => rawurldecode($m[1]),
				'mpn' => rawurldecode($m[2])
			];
		}

		// Compact form: /ProductDetail/<vendor>-<mpn>. Strip the leading
		// numeric supplier code (Mouser-internal — e.g. 650 = Bourns,
		// 595 = Texas Instruments, …) so we end up with just the real
		// MPN; the aggregator search will find a matching candidate
		// without the manufacturer hint (no usable mfr in this URL shape).
		if (preg_match('~^/ProductDetail/\d{3,4}-([^/?#]+)/?$~i', $path, $m) === 1) {
			return [
				'manufacturer' => '',
				'mpn' => rawurldecode($m[1])
			];
		}

		return null;
	}
}
