<?php

namespace Limas\Service\Integration\InfoProvider;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Nette\Utils\Json;
use Nette\Utils\Strings;
use Psr\Cache\CacheItemInterface;
use Symfony\Contracts\Cache\CacheInterface;


/**
 * Mouser Electronics API service — Search API V2
 *
 * API Documentation (Swagger): https://api.mouser.com/api/docs/V2
 *
 * Auth: API key passed as ?apiKey=... query parameter
 *
 * V2 endpoints (subset of Search API only):
 *   POST /api/v2/search/keywordandmanufacturer  — keyword search; manufacturerName optional
 *   POST /api/v2/search/partnumberandmanufacturer — part-number search; up to 10 PNs pipe-separated
 *   GET  /api/v2/search/manufacturerlist
 *
 * Response shape is identical to V1 (SearchResults.Parts[] of MouserPart).
 */
class MouserService
{
	private const string MOUSER_ENDPOINT = 'https://api.mouser.com';


	public function __construct(
		private readonly CacheInterface                $mouserCache,
		#[\SensitiveParameter] private readonly string $clientKey,
		private readonly int                           $limit = 50
	)
	{
	}

	/**
	 * Keyword search. manufacturerName is optional (passing null/empty = global search).
	 *
	 * @param string $q
	 * @param int $pageNumber 1-based page number (V2 uses pageNumber, not startingRecord)
	 * @param string|null $manufacturerName Optional manufacturer filter; get exact name via getManufacturerList()
	 */
	public function searchByKeyword(string $q, int $pageNumber = 1, ?string $manufacturerName = null): array
	{
		$request = [
			'keyword' => Strings::substring($q, 0, 40),
			'records' => $this->limit,
			'pageNumber' => $pageNumber
		];
		if ($manufacturerName !== null && $manufacturerName !== '') {
			$request['manufacturerName'] = $manufacturerName;
		}

		$body = (new Client)->request(
			'POST',
			self::MOUSER_ENDPOINT . '/api/v2/search/keywordandmanufacturer',
			[
				RequestOptions::JSON => ['SearchByKeywordMfrNameRequest' => $request],
				RequestOptions::QUERY => ['apiKey' => $this->clientKey]
			]
		)->getBody();

		return $this->parseResponse((string)$body);
	}

	/**
	 * Part-number search. Up to 10 part numbers may be passed pipe-separated.
	 *
	 * @param string $q One MPN/Mouser PN, or multiple separated by '|'
	 * @param string $partSearchOptions 'Exact' or 'None' (default None — fuzzy)
	 * @param string|null $manufacturerName Optional manufacturer filter
	 */
	public function searchByPartnumber(string $q, string $partSearchOptions = 'Exact', ?string $manufacturerName = null): array
	{
		$request = [
			'mouserPartNumber' => Strings::substring($q, 0, 400), // 10 × 40 chars
			'partSearchOptions' => $partSearchOptions
		];
		if ($manufacturerName !== null && $manufacturerName !== '') {
			$request['manufacturerName'] = $manufacturerName;
		}

		$body = (new Client)->request(
			'POST',
			self::MOUSER_ENDPOINT . '/api/v2/search/partnumberandmanufacturer',
			[
				RequestOptions::JSON => ['SearchByPartMfrNameRequest' => $request],
				RequestOptions::QUERY => ['apiKey' => $this->clientKey]
			]
		)->getBody();

		return $this->parseResponse((string)$body);
	}

	/**
	 * Full manufacturer-name list — useful for resolving fuzzy manufacturer names
	 */
	public function getManufacturerList(): array
	{
		$body = (new Client)->request(
			'GET',
			self::MOUSER_ENDPOINT . '/api/v2/search/manufacturerlist',
			[RequestOptions::QUERY => ['apiKey' => $this->clientKey]]
		)->getBody();

		$data = json_decode((string)$body, true, 512, JSON_THROW_ON_ERROR);
		if (is_array($data['Errors'] ?? null) && $data['Errors'] !== []) {
			throw new \RuntimeException('Mouser API error: ' . Json::encode($data['Errors']));
		}
		return $data;
	}

	private function parseResponse(string $body): array
	{
		$parts = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

		if (is_array($parts['Errors'] ?? null) && $parts['Errors'] !== []) {
			throw new \RuntimeException('Mouser API error: ' . Json::encode($parts['Errors']));
		}
		if (!is_array($parts['SearchResults'] ?? null)) {
			throw new \RuntimeException('Mouser API unexpected response: ' . substr($body, 0, 500));
		}

		foreach ($parts['SearchResults']['Parts'] ?? [] as $part) {
			$id = $part['MouserPartNumber'] ?? null;
			if ($id === null) {
				continue;
			}
			$this->mouserCache->delete($id);
			$this->mouserCache->get($id, static fn(CacheItemInterface $item) => Json::encode($part));
		}

		return $parts;
	}
}
