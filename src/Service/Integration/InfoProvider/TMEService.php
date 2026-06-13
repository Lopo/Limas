<?php

namespace Limas\Service\Integration\InfoProvider;

use Nette\Utils\Json;
use Psr\Cache\CacheItemInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;


/**
 * TME (Transfer Multisort Elektronik) API service — v2 OAuth 2.0
 *
 * API Documentation: https://api-doc.tme.eu/v2
 *
 * Migrated 2026-05-21 to Symfony HttpClient. Major win for TME specifically:
 * its detail flow needs 3 endpoints (`/products` + `/products/parameters` +
 * `/products/data`) which previously ran sequentially. With async variants
 * the adapter can dispatch all three and curl_multi pipelines them — total
 * time becomes ≈ max() instead of sum().
 *
 * Auth: POST /auth/token with HTTP Basic Auth (Token as username, App Secret
 * as password) + body grant_type=client_credentials → access_token. Tokens
 * are short-lived (≈300s), cached.
 *
 * Endpoints used:
 *  - GET /products/search    — search by phrase + scope[]=products
 *  - GET /products           — lookup by symbols[] or mpns[]; full product detail
 *  - GET /products/parameters — technical parameters per symbol
 *  - GET /products/files     — datasheets, photos, presentation assets per symbol
 *  - GET /products/data      — stock/prices/delivery per symbol + amount
 */
class TMEService
{
	private const string TME_ENDPOINT = 'https://api.tme.eu';


	public function __construct(
		private readonly HttpClientInterface           $httpClient,
		private readonly CacheInterface                $tmeCache,
		#[\SensitiveParameter] private readonly string $token,
		#[\SensitiveParameter] private readonly string $appSecret,
		private readonly int                           $limit = 20,
		private readonly string                        $country = 'SK'
	)
	{
	}

	/**
	 * Returns a cached OAuth2 access token, fetching a new one when expired.
	 * Token fetch stays sync — it's once-per-5-minutes and downstream calls
	 * need the bearer up front; making it async would complicate caching.
	 */
	public function getAccessToken(): string
	{
		return $this->tmeCache->get('tmeAccessToken', function (CacheItemInterface $item) {
			$response = $this->httpClient->request('POST', self::TME_ENDPOINT . '/auth/token', [
				'auth_basic' => [$this->token, $this->appSecret],
				'body' => ['grant_type' => 'client_credentials']
			]);

			$data = $response->toArray();

			if (!is_string($data['access_token'] ?? null) || $data['access_token'] === '') {
				throw new \RuntimeException('TME getToken returned no access_token');
			}

			$item->expiresAfter(max((int)($data['expires_in'] ?? 300) - 30, 60));

			return $data['access_token'];
		});
	}

	/**
	 * Full-text search across TME catalogue
	 *
	 * @param string $phrase 2..40 chars
	 * @param int $page starting from 1
	 * @param array<string> $scope subset of {'products', 'parameters', 'counters'}
	 */
	public function searchByKeyword(string $phrase, int $page = 1, array $scope = ['products']): array
	{
		$data = $this->searchByKeywordAsync($phrase, $page, $scope)->toArray(false);
		foreach ($data['data']['products']['elements'] ?? [] as $product) {
			$symbol = $product['symbol'] ?? null;
			if ($symbol === null) {
				continue;
			}
			$this->tmeCache->delete('product_' . $symbol);
			$this->tmeCache->get('product_' . $symbol, static fn(CacheItemInterface $item) => Json::encode($product));
		}
		return $data;
	}

	public function searchByKeywordAsync(string $phrase, int $page = 1, array $scope = ['products']): ResponseInterface
	{
		return $this->httpClient->request('GET', self::TME_ENDPOINT . '/products/search', [
			'headers' => $this->bearerHeaders(),
			'query' => [
				'country' => $this->country,
				'phrase' => $phrase,
				'scope' => $scope,
				'limit' => $this->limit,
				'page' => $page
			]
		]);
	}

	/**
	 * Lookup products by TME symbols (internal IDs) or manufacturer part numbers
	 *
	 * @param array<string>|null $symbols TME internal SKUs (e.g. "1N4148")
	 * @param array<string>|null $mpns Manufacturer part numbers (e.g. "ATMEGA328P-PU")
	 */
	public function getProducts(?array $symbols = null, ?array $mpns = null): array
	{
		return $this->getProductsAsync($symbols, $mpns)->toArray(false);
	}

	public function getProductsAsync(?array $symbols = null, ?array $mpns = null): ResponseInterface
	{
		if ($symbols === null && $mpns === null) {
			throw new \InvalidArgumentException('TME getProducts: provide symbols[] or mpns[]');
		}
		$query = ['country' => $this->country];
		if ($symbols !== null) {
			$query['symbols'] = $symbols;
		}
		if ($mpns !== null) {
			$query['mpns'] = $mpns;
		}
		return $this->httpClient->request('GET', self::TME_ENDPOINT . '/products', [
			'headers' => $this->bearerHeaders(),
			'query' => $query
		]);
	}

	/**
	 * @param array<string> $symbols TME symbols (max 50)
	 */
	public function getProductParameters(array $symbols): array
	{
		return $this->getProductParametersAsync($symbols)->toArray(false);
	}

	public function getProductParametersAsync(array $symbols): ResponseInterface
	{
		return $this->httpClient->request('GET', self::TME_ENDPOINT . '/products/parameters', [
			'headers' => $this->bearerHeaders(),
			'query' => ['country' => $this->country, 'symbols' => $symbols],
		]);
	}

	/**
	 * @param array<string> $symbols TME symbols (max 50)
	 */
	public function getProductFiles(array $symbols): array
	{
		return $this->getProductFilesAsync($symbols)->toArray(false);
	}

	public function getProductFilesAsync(array $symbols): ResponseInterface
	{
		return $this->httpClient->request('GET', self::TME_ENDPOINT . '/products/files', [
			'headers' => $this->bearerHeaders(),
			'query' => ['country' => $this->country, 'symbols' => $symbols]
		]);
	}

	/**
	 * @param array<string> $symbols TME symbols (max 50)
	 * @param array<int> $amounts Required iff scope includes 'delivery' / 'delivery_confirmed'.
	 * @param array<string> $scope subset of {'prices', 'stock', 'delivery', 'delivery_confirmed'}
	 */
	public function getProductData(array $symbols, array $amounts = [], array $scope = ['prices', 'stock']): array
	{
		return $this->getProductDataAsync($symbols, $amounts, $scope)->toArray(false);
	}

	public function getProductDataAsync(array $symbols, array $amounts = [], array $scope = ['prices', 'stock']): ResponseInterface
	{
		$needsAmounts = in_array('delivery', $scope, true) || in_array('delivery_confirmed', $scope, true);
		$query = [
			'country' => $this->country,
			'scope' => $scope,
			'symbols' => $symbols
		];
		if ($needsAmounts) {
			if ($amounts === []) {
				throw new \InvalidArgumentException('TME getProductData: amounts[] required when scope includes delivery/delivery_confirmed');
			}
			$query['amounts'] = $amounts;
		}
		return $this->httpClient->request('GET', self::TME_ENDPOINT . '/products/data', [
			'headers' => $this->bearerHeaders(),
			'query' => $query
		]);
	}

	/**
	 * @return array<string, string>
	 */
	private function bearerHeaders(): array
	{
		return [
			'Authorization' => 'Bearer ' . $this->getAccessToken(),
			'Accept' => 'application/json'
		];
	}
}
