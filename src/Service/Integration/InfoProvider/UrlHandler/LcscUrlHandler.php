<?php

namespace Limas\Service\Integration\InfoProvider\UrlHandler;

use Limas\Service\Integration\InfoProvider\Contract\URLHandlerInterface;


/**
 * LCSC product-detail URL parser
 *
 * LCSC URLs come in two shapes that the regex covers in one pass:
 *   /product-detail/<Cxxxxx>.html
 *     short link, e.g. /product-detail/C8512.html
 *   /product-detail/<category>_<mfr>-<mpn>_<Cxxxxx>.html
 *     full SEO slug, e.g. /product-detail/Bipolar-Transistors-BJT_onsemi-BC547BBU_C896669.html
 *
 * We extract only the trailing `Cxxxxx` LCSC part code (their unique
 * sourceSku). The category / mfr / mpn slugs in the long form are
 * marketing-flavoured and not reliably parseable into clean strings —
 * the aggregator instead resolves Cxxxxx → mfr+mpn via LcscAdapter's
 * own getDetails() call.
 *
 * The aggregator handles the "sourceSku-only" case by calling the
 * matching adapter's getDetails(sourceSku) to lift mfr + mpn out of
 * the live record, then runs the standard MPN search on the filled-in
 * values. Costs one extra HTTP call to wmsc.lcsc.com (cached after
 * first hit) — small price for proper LCSC URL support.
 */
final class LcscUrlHandler
	implements URLHandlerInterface
{
	public function getName(): string
	{
		return 'lcsc';
	}

	/** @return string[] */
	public function getHandledDomains(): array
	{
		return ['lcsc.com'];
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
		// `Cxxxxx.html` at end of path, optionally preceded by `_` (SEO
		// slug) or `/` (short link). Case-insensitive on the C prefix.
		if (preg_match('~[/_](C\d+)\.html$~i', $path, $m) !== 1) {
			return null;
		}
		// LCSC URL carries only their internal code — caller resolves
		// mpn + manufacturer via getDetails(sourceSku)
		return [
			'manufacturer' => '',
			'mpn' => '',
			'sourceSku' => strtoupper($m[1])
		];
	}
}
