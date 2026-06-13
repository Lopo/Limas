<?php

namespace Limas\Service\Integration\InfoProvider\UrlHandler;

use Limas\Service\Integration\InfoProvider\Contract\URLHandlerInterface;


/**
 * TME product-detail URL parser
 *
 * TME URLs follow:
 *   /<lang>/details/<tme-symbol>/<category>/<mfr>/<mpn>/
 *     e.g. /sk/details/zl201-40g/konektory-hrebienky/connfly/ds1021-1-40sf11-b/
 *   /<lang>/details/<tme-symbol>/
 *     short form, no mfr/mpn slugs
 *
 * The 3rd segment `<tme-symbol>` is TME's own part identifier — what
 * TmeAdapter uses as `sourceSku`. When the full form is present we
 * also lift mfr + mpn from the path; otherwise the aggregator falls
 * back to TmeAdapter::getDetails(symbol) to fill them in.
 *
 * Path slugs are lowercase + hyphenated; we uppercase the symbol so
 * TME's API matches (their cached SKUs are case-sensitive uppercase).
 * MPN slugs preserve the URL casing — search-by-MPN is
 * case-insensitive on the aggregator side so this is fine.
 */
final class TmeUrlHandler
	implements URLHandlerInterface
{
	public function getName(): string
	{
		return 'tme';
	}

	/** @return string[] */
	public function getHandledDomains(): array
	{
		return ['tme.eu'];
	}

	/**
	 * @return array{mpn: string, manufacturer: string, sourceSku?: string}|null
	 */
	public function tryExtractFromURL(string $url): ?array
	{
		$path = parse_url($url, PHP_URL_PATH);
		if (!is_string($path)) {
			return null;
		}

		// Long form: /<lang>/details/<symbol>/<cat>/<mfr>/<mpn>/
		if (preg_match('~^/[a-z]{2}/details/([^/]+)/[^/]+/([^/]+)/([^/]+)/?$~i', $path, $m) === 1) {
			return [
				'manufacturer' => rawurldecode($m[2]),
				'mpn' => rawurldecode($m[3]),
				'sourceSku' => strtoupper(rawurldecode($m[1]))
			];
		}

		// Short form: /<lang>/details/<symbol>/
		if (preg_match('~^/[a-z]{2}/details/([^/]+)/?$~i', $path, $m) === 1) {
			return [
				'manufacturer' => '',
				'mpn' => '',
				'sourceSku' => strtoupper(rawurldecode($m[1]))
			];
		}

		return null;
	}
}
