<?php

namespace Limas\Service\Integration\InfoProvider;

use Nette\Utils\Json;
use Nette\Utils\Strings;
use Psr\Cache\CacheItemInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;


/**
 * Farnell/element14 API service
 *
 * API Documentation: https://partner.element14.com/docs/Product_Search_API_REST__Description
 *
 * Migrated 2026-05-21 from Guzzle to Symfony HttpClient so the aggregator can
 * fan out across providers in parallel — curl_multi pipelines all pending
 * responses, so awaiting one no longer blocks the others.
 *
 * Public API surface:
 *  - `searchByKeyword()` / `searchByPartNumber()` — sync wrappers (existing
 *    callers continue to work).
 *  - `searchByKeywordAsync()` / `searchByPartNumberAsync()` — return lazy
 *    Symfony `ResponseInterface`; await with `->toArray()`. Use from the
 *    aggregator to dispatch concurrent requests.
 */
class FarnellService
{
	private const string FARNELL_ENDPOINT = 'https://api.element14.com/catalog/products';


	public function __construct(
		private readonly HttpClientInterface            $httpClient,
		private readonly CacheInterface                 $farnellCache,
		#[\SensitiveParameter] private readonly string  $apiKey,
		private readonly string                         $storeId = 'sk.farnell.com',
		private readonly int                            $limit = 25,
		#[\SensitiveParameter] private readonly ?string $customerId = null,
		#[\SensitiveParameter] private readonly ?string $secretKey = null
	)
	{
	}

	public function searchByKeyword(string $keyword, int $offset = 0): array
	{
		return $this->awaitAndCache($this->searchByKeywordAsync($keyword, $offset));
	}

	public function searchByKeywordAsync(string $keyword, int $offset = 0): ResponseInterface
	{
		return $this->httpClient->request('GET', self::FARNELL_ENDPOINT, [
			'query' => $this->addAuthenticationParams([
				'term' => 'any:' . Strings::substring($keyword, 0, 100),
				'storeInfo.id' => $this->storeId,
				'resultsSettings.offset' => $offset,
				'resultsSettings.numberOfResults' => $this->limit,
				'resultsSettings.responseGroup' => 'large',
				'callInfo.responseDataFormat' => 'json'
			]),
			'headers' => ['Accept' => 'application/json']
		]);
	}

	public function searchByPartNumber(string $partNumber, bool $isManufacturerPart = false): array
	{
		return $this->awaitAndCache($this->searchByPartNumberAsync($partNumber, $isManufacturerPart));
	}

	public function searchByPartNumberAsync(string $partNumber, bool $isManufacturerPart = false): ResponseInterface
	{
		$searchTerm = $isManufacturerPart
			? 'manuPartNum:' . $partNumber
			: 'id:' . $partNumber;

		return $this->httpClient->request('GET', self::FARNELL_ENDPOINT, [
			'query' => $this->addAuthenticationParams([
				'term' => $searchTerm,
				'storeInfo.id' => $this->storeId,
				'resultsSettings.responseGroup' => 'large',
				'callInfo.responseDataFormat' => 'json'
			]),
			'headers' => ['Accept' => 'application/json']
		]);
	}

	/**
	 * Await a lazy response, decode JSON, and warm the per-SKU product cache.
	 * Centralised so both sync wrappers behave identically.
	 */
	public function awaitAndCache(ResponseInterface $response): array
	{
		$data = $response->toArray(false);
		foreach (
			$data['keywordSearchReturn']['products']
			?? $data['premierFarnellPartNumberReturn']['products']
			?? $data['manufacturerPartNumberSearchReturn']['products']
			?? [] as $product
		) {
			$sku = $product['sku'] ?? null;
			if (is_string($sku) && $sku !== '') {
				$this->farnellCache->delete('product_' . $sku);
				$this->farnellCache->get('product_' . $sku, static fn(CacheItemInterface $item) => Json::encode($product));
			}
		}
		return $data;
	}

	private function addAuthenticationParams(array $params): array
	{
		$params['callInfo.apiKey'] = $this->apiKey;

		if ($this->customerId !== null && $this->secretKey !== null && $this->customerId !== '' && $this->secretKey !== '') {
			$timestamp = gmdate('Y-m-d\TH:i:s\Z');
			$params['callInfo.omitXmlSchema'] = 'false';
			$params['userInfo.customerInfo.customerId'] = $this->customerId;
			$params['userInfo.timestamp'] = $timestamp;

			$signatureString = $timestamp . $this->customerId;
			$signature = base64_encode(hash_hmac('sha256', $signatureString, $this->secretKey, true));
			$params['userInfo.signature'] = $signature;
		}

		return $params;
	}

	public function getStoreId(): string
	{
		return $this->storeId;
	}
}
