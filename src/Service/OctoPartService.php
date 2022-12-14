<?php

namespace Limas\Service;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Nette\Utils\Json;
use Predis\Client as PredisClient;


class OctoPartService
{
	private const OCTOPART_ENDPOINT = 'https://api.nexar.com/graphql/';
	private const OCTOPART_QUERY = <<<'EOD'
    query MyPartSearch(
  $q: String!
  $filters: Map
  $limit: Int!
  $start: Int
  $country: String = "DE"
  $currency: String = "EUR"
) {
  supSearch(
    q: $q
    filters: $filters
    limit: $limit
    start: $start
    country: $country
    currency: $currency
  ) {
    hits

    results {
      part {
        id
        mpn
        slug
        shortDescription
        counts
        manufacturer {
          name
        }
        bestDatasheet {
          name
          url
          creditString
          creditUrl
          pageCount
          mimeType
        }
        bestImage {
          url
        }
        specs {
          attribute {
            name
            group
          }
          displayValue
        }
        documentCollections {
          name
          documents {
            name
            url
            creditString
            creditUrl
          }
        }
        descriptions {
          creditString
          text
        }
        cad {
          addToLibraryUrl
        }
        referenceDesigns {
          name
          url
        }
        sellers {
          company {
            homepageUrl
            isVerified
            name
            slug
          }
          isAuthorized
          isBroker
          isRfq
          offers {
            clickUrl
            inventoryLevel
            moq
            packaging
            prices {
              conversionRate
              convertedCurrency
              convertedPrice
              currency
              price
              quantity
            }
            sku
            updated
          }
        }
      }
    }
  }
}
EOD;
	private const OCTOPART_PARTQUERY = <<<'EOD'
    query MyPartSearch(
  $id: String!
  $country: String = "DE"
  $currency: String = "EUR"
) {
  supParts(ids: [$id], country: $country, currency: $currency) {
    id
    mpn
    slug
    shortDescription
    counts
    manufacturer {
      name
    }
    bestDatasheet {
      name
      url
      creditString
      creditUrl
      pageCount
      mimeType
    }
    bestImage {
      url
    }
    specs {
      attribute {
        name
        group
      }
      displayValue
    }
    documentCollections {
      name
      documents {
        name
        url
        creditString
        creditUrl
      }
    }
    descriptions {
      creditString
      text
    }
    cad {
      addToLibraryUrl
    }
    referenceDesigns {
      name
      url
    }
    sellers {
      company {
        homepageUrl
        isVerified
        name
        slug
      }
      isAuthorized
      isBroker
      isRfq
      offers {
        clickUrl
        inventoryLevel
        moq
        packaging
        prices {
          conversionRate
          convertedCurrency
          convertedPrice
          currency
          price
          quantity
        }
        sku
        updated
      }
    }
  }
}
EOD;
	private const NEXAR_AUTHORITY = 'https://identity.nexar.com/';


	public function __construct(
		private readonly string $clientId,
		private readonly string $clientSecret,
		private readonly int    $limit = 3
	)
	{
	}

	public function getPartByUID(string $uid): object
	{
		try {
			$redisclient = new PredisClient;
			$redisclient->connect();
			if (null !== ($part = $redisclient->get($uid))) {
				return Json::decode($part);
			}
			$redisclient->disconnect();
		} catch (\Exception $e) {
		}

		$body = (new Client)->request(
			'POST',
			self::OCTOPART_ENDPOINT,
			[
				RequestOptions::HEADERS => [
					'Authorization' => 'Bearer ' . $this->getToken()
				],
				RequestOptions::JSON => [
					'query' => self::OCTOPART_PARTQUERY,
					'operationName' => 'MyPartSearch',
					'variables' => [
						'id' => $uid
					]
				]
			])->getBody();

		$data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

		return $data['data']['parts'][0];
	}

	public function getPartyByQuery($q, int $startpage = 1): array
	{
		$body = (new Client)->request(
			'POST',
			self::OCTOPART_ENDPOINT,
			[
				RequestOptions::HEADERS => [
					'Authorization' => 'Bearer ' . $this->getToken()
				],
				RequestOptions::JSON => [
					'query' => self::OCTOPART_QUERY,
					'operationName' => 'MyPartSearch',
					'variables' => [
						'q' => $q,
						'limit' => $this->limit,
						'start' => ($startpage - 1) * $this->limit // 0-based
					]
				]
			])->getBody();

		$parts = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

		// work around the low number of allowed accesses to octopart's api
		try {
			$redisclient = new PredisClient;
			$redisclient->connect();
			foreach ($parts['data']['supSearch']['results'] as $result) {
				$redisclient->set($result['part']['id'], Json::encode($result['part']));
			}
			$redisclient->disconnect();
		} catch (\Exception $e) {
		}

		return $parts;
	}

	private function getToken(): string
	{
		try {
			$redisclient = new PredisClient;
			$redisclient->connect();
			$token = $redisclient->get('nexarToken');
			$redisclient->disconnect();
			if ($token !== null) {
				return $token;
			}
		} catch (\Exception $e) {
		}
		$response = (new Client)->request(
			'POST',
			self::NEXAR_AUTHORITY . 'connect/token',
			[
				RequestOptions::ALLOW_REDIRECTS => false,
				RequestOptions::FORM_PARAMS => [
					'grant_type' => 'client_credentials',
					'client_id' => $this->clientId,
					'client_secret' => $this->clientSecret
				]
			]
		);
		if ($response->getStatusCode() !== 200) {
			throw new \RuntimeException('Octopart/Nexus getToken');
		}
		$resp = Json::decode($response->getBody());
		try {
			$redisclient = new PredisClient;
			$redisclient->connect();
			$redisclient->set('nexarToken', $resp->access_token, 'EX', $resp->expires_in - 60);
			$redisclient->disconnect();
		} catch (\Exception $e) {
		}
		return $resp->access_token;
	}
}
