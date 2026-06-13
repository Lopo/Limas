<?php

namespace Limas\Service\Integration\InfoProvider;

use Nette\Utils\Json;
use Nette\Utils\Strings;
use Psr\Cache\CacheItemInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;


/**
 * DigiKey API service — Product Information V4
 *
 * Portal: https://developer.digikey.com/products/product-information-v4
 *
 * Migrated 2026-05-21 to Symfony HttpClient for curl_multi parallelism (the
 * aggregator fans out to several providers + several details concurrently).
 *
 * Async surface: `searchByKeywordAsync` / `searchByPartNumberAsync` return
 * lazy `ResponseInterface`s. The sync wrappers stay as thin `->toArray()`
 * facades for existing callers.
 *
 * Auth: OAuth2 client_credentials at /v1/oauth2/token. Access token cached.
 * Token fetch stays sync because it's hit-once-per-hour and downstream
 * requests need the bearer in headers — keeping it sync simplifies caching.
 */
class DigiKeyService
{
	private const string DIGIKEY_ENDPOINT_PRODUCTION = 'https://api.digikey.com';
	private const string DIGIKEY_ENDPOINT_SANDBOX = 'https://sandbox-api.digikey.com';

	private readonly string $endpoint;


	public function __construct(
		private readonly HttpClientInterface           $httpClient,
		private readonly CacheInterface                $digikeyCache,
		#[\SensitiveParameter] private readonly string $clientID,
		#[\SensitiveParameter] private readonly string $clientSecret,
		private readonly int                           $limit = 50,
		private readonly bool                          $sandbox = false,
		private readonly string                        $localeSite = 'SK',
		private readonly string                        $localeLanguage = 'en',
		private readonly string                        $localeCurrency = 'EUR'
	)
	{
		$this->endpoint = $this->sandbox ? self::DIGIKEY_ENDPOINT_SANDBOX : self::DIGIKEY_ENDPOINT_PRODUCTION;
	}

	public function searchByKeyword(string $keyword, int $offset = 0): array
	{
		return $this->awaitAndCache($this->searchByKeywordAsync($keyword, $offset), keywordSearch: true);
	}

	public function searchByKeywordAsync(string $keyword, int $offset = 0): ResponseInterface
	{
		return $this->httpClient->request('POST', $this->endpoint . '/products/v4/search/keyword', [
			'json' => [
				'Keywords' => Strings::substring($keyword, 0, 250),
				'Limit' => $this->limit,
				'Offset' => $offset
			],
			'headers' => $this->apiHeaders()
		]);
	}

	public function searchByPartNumber(string $partNumber, ?int $manufacturerId = null): array
	{
		return $this->awaitAndCache($this->searchByPartNumberAsync($partNumber, $manufacturerId), keywordSearch: false);
	}

	public function searchByPartNumberAsync(string $partNumber, ?int $manufacturerId = null): ResponseInterface
	{
		return $this->httpClient->request('GET',
			$this->endpoint . '/products/v4/search/' . rawurlencode($partNumber) . '/productdetails',
			[
				'headers' => $this->apiHeaders(),
				'query' => $manufacturerId !== null ? ['manufacturerId' => $manufacturerId] : []
			]
		);
	}

	/**
	 * Decode response and warm per-MPN product cache. `keywordSearch=true` walks
	 * the `Products[]` envelope; `false` treats the response as a single product.
	 */
	public function awaitAndCache(ResponseInterface $response, bool $keywordSearch): array
	{
		$data = $response->toArray(false);
		$items = $keywordSearch ? ($data['Products'] ?? []) : [$data];
		foreach ($items as $product) {
			$id = $product['ManufacturerProductNumber'] ?? null;
			if ($id === null) {
				continue;
			}
			$this->digikeyCache->delete('product_' . $id);
			$this->digikeyCache->get('product_' . $id, static fn(CacheItemInterface $item) => Json::encode($product));
		}
		return $data;
	}

	private function getToken(): string
	{
		return $this->digikeyCache->get('digikeyToken', function (CacheItemInterface $item) {
			$response = $this->httpClient->request('POST', $this->endpoint . '/v1/oauth2/token', [
				'body' => [
					'client_id' => $this->clientID,
					'client_secret' => $this->clientSecret,
					'grant_type' => 'client_credentials'
				],
				'headers' => ['Content-Type' => 'application/x-www-form-urlencoded']
			]);

			$data = $response->toArray();

			$ttl = (int)($data['expires_in'] ?? 3600) - 300;
			$item->expiresAfter(max($ttl, 60));

			return $data['access_token'];
		});
	}

	/**
	 * @return array<string, string>
	 */
	private function apiHeaders(): array
	{
		return [
			'Authorization' => 'Bearer ' . $this->getToken(),
			'X-DIGIKEY-Client-Id' => $this->clientID,
			'Accept' => 'application/json',
			'Content-Type' => 'application/json',
			'X-DIGIKEY-Locale-Site' => $this->localeSite,
			'X-DIGIKEY-Locale-Language' => $this->localeLanguage,
			'X-DIGIKEY-Locale-Currency' => $this->localeCurrency
		];
	}
}
