<?php

namespace Limas\Service\Integration\InfoProvider;

use Nette\Utils\Json;
use Nette\Utils\Strings;
use Psr\Cache\CacheItemInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;


/**
 * Mouser Electronics API service — Search API V2
 *
 * API Documentation (Swagger): https://api.mouser.com/api/docs/V2
 *
 * Auth: API key passed as ?apiKey=... query parameter. The key must be
 * activated once via the emailed verification code before it works — an
 * unactivated key returns "Invalid unique identifier" on every call.
 *
 * Endpoints used:
 *   POST /api/v2/search/keyword    — SearchByKeywordRequest, max 50 parts
 *   POST /api/v2/search/partnumber — SearchByPartRequest, max 50 parts
 *   GET  /api/v2/search/manufacturerlist
 *
 * Response shape: SearchResults.Parts[] of MouserPart (see MouserAdapter for
 * the field map). Errors come back HTTP-200 with a non-empty top-level
 * `Errors` array rather than an error status.
 *
 * Public API surface:
 *  - `searchByKeyword()` / `searchByPartnumber()` — sync wrappers.
 *  - `searchByKeywordAsync()` / `searchByPartnumberAsync()` — return lazy
 *    Symfony `ResponseInterface`; feed to `awaitAndCache()`.
 */
class MouserService
{
	private const string MOUSER_ENDPOINT = 'https://api.mouser.com';


	public function __construct(
		private readonly HttpClientInterface           $httpClient,
		private readonly CacheInterface                $mouserCache,
		#[\SensitiveParameter] private readonly string $clientKey,
		private readonly int                           $limit = 50
	)
	{
	}

	/**
	 * True when an API key is present. Note this only checks the key exists —
	 * an unactivated key still passes here but fails at the API.
	 */
	public function isConfigured(): bool
	{
		return $this->clientKey !== '';
	}

	/**
	 * Keyword search — no manufacturer required. Returns up to `$this->limit`
	 * parts ranked by Mouser's relevance sort.
	 */
	public function searchByKeyword(string $q): array
	{
		return $this->awaitAndCache($this->searchByKeywordAsync($q));
	}

	public function searchByKeywordAsync(string $q): ResponseInterface
	{
		return $this->httpClient->request(
			'POST',
			self::MOUSER_ENDPOINT . '/api/v2/search/keyword',
			[
				'query' => ['apiKey' => $this->clientKey],
				'json' => ['SearchByKeywordRequest' => [
					'keyword' => Strings::substring($q, 0, 40),
					'records' => $this->limit,
					'startingRecord' => 0,
					'searchOptions' => '',
					'searchWithYourSignUpLanguage' => ''
				]]
			]
		);
	}

	/**
	 * Part-number search. Matches both Mouser part numbers and manufacturer
	 * part numbers. `$partSearchOptions` = 'Exact' pins exact matches only,
	 * 'None' (default) is fuzzy.
	 */
	public function searchByPartnumber(string $q, string $partSearchOptions = 'None'): array
	{
		return $this->awaitAndCache($this->searchByPartnumberAsync($q, $partSearchOptions));
	}

	public function searchByPartnumberAsync(string $q, string $partSearchOptions = 'None'): ResponseInterface
	{
		return $this->httpClient->request(
			'POST',
			self::MOUSER_ENDPOINT . '/api/v2/search/partnumber',
			[
				'query' => ['apiKey' => $this->clientKey],
				'json' => ['SearchByPartRequest' => [
					'mouserPartNumber' => Strings::substring($q, 0, 40),
					'partSearchOptions' => $partSearchOptions
				]]
			]
		);
	}

	/**
	 * Await a lazy response, decode JSON, warm the per-part cache keyed by
	 * MouserPartNumber. Mirrors FarnellService::awaitAndCache. Does NOT throw
	 * on a Mouser `Errors` payload — the adapter degrades to an empty Parts
	 * list so one misconfigured source can't abort the aggregator fan-out.
	 */
	public function awaitAndCache(ResponseInterface $response): array
	{
		$data = $response->toArray(false);

		foreach ($data['SearchResults']['Parts'] ?? [] as $part) {
			$id = $part['MouserPartNumber'] ?? null;
			if (is_string($id) && $id !== '') {
				$this->mouserCache->delete('product_' . rawurlencode($id));
				$this->mouserCache->get('product_' . rawurlencode($id), static fn(CacheItemInterface $item) => Json::encode($part));
			}
		}

		return $data;
	}

	/**
	 * Full manufacturer-name list — useful for resolving fuzzy manufacturer
	 * names. Throws on an API error since callers expect a usable list.
	 */
	public function getManufacturerList(): array
	{
		$data = $this->httpClient->request(
			'GET',
			self::MOUSER_ENDPOINT . '/api/v2/search/manufacturerlist',
			['query' => ['apiKey' => $this->clientKey]]
		)->toArray(false);

		if (is_array($data['Errors'] ?? null) && $data['Errors'] !== []) {
			throw new \RuntimeException('Mouser API error: ' . Json::encode($data['Errors']));
		}

		return $data;
	}
}
