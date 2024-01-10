<?php

namespace Limas\Service;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Nette\Utils\Json;
use Psr\Cache\CacheItemInterface;
use Symfony\Contracts\Cache\CacheInterface;


class OctoPartService
{
	private const OCTOPART_ENDPOINT = 'https://api.nexar.com/graphql/';
	private const OCTOPART_QUERY = <<<'EOD'
    query MyPartSearch(
  $q: String!
  $filters: Map
  $limit: Int!
  $start: Int
  $country: String!
  $currency: String!
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
        manufacturerUrl
        freeSampleUrl
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
            mimeType
          }
        }
        descriptions {
          creditString
          text
        }
        cad {
          addToLibraryUrl
          has3dModel
          hasAltium
          hasEagle
          hasOrcad
          hasKicad
          downloadUrlAltium
          downloadUrlEagle
          downloadUrlOrcad
          downloadUrlKicad
          footprintImageUrl
          symbolImageUrl
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
            displayFlag
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
  $country: String!
  $currency: String!
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
    manufacturerUrl
    freeSampleUrl
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
        mimeType
      }
    }
    descriptions {
      creditString
      text
    }
    cad {
      addToLibraryUrl
      has3dModel
      hasAltium
      hasEagle
      hasOrcad
      hasKicad
      downloadUrlAltium
      downloadUrlEagle
      downloadUrlOrcad
      downloadUrlKicad
      footprintImageUrl
      symbolImageUrl
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
        displayFlag
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
		private readonly CacheInterface                $octopartCache,
		#[\SensitiveParameter] private readonly string $clientId,
		#[\SensitiveParameter] private readonly string $clientSecret,
		private readonly int                           $limit = 3,
		private readonly string                        $country = 'DE',
		private readonly string                        $currency = 'EUR'
	)
	{
	}

	public function getPartByUID(string $uid): object
	{
		return Json::decode(
			$this->octopartCache->get($uid, function (CacheItemInterface $item) use ($uid) {
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
								'id' => $uid,
								'country' => $this->country,
								'currency' => $this->currency
							]
						]
					])->getBody();
				$data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
				return $data['data']['parts'][0];
			})
		);
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
						'start' => ($startpage - 1) * $this->limit, // 0-based
						'country' => $this->country,
						'currency' => $this->currency
					]
				]
			])->getBody();

		$parts = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

		// work around the low number of allowed accesses to octopart's api
		foreach ($parts['data']['supSearch']['results'] as $result) {
			$this->octopartCache->get($result['part']['id'], function (CacheItemInterface $item) use ($result) {
				return Json::encode($result['part']);
			});
		}

		return $parts;
	}

	private function getToken(): string
	{
		return $this->octopartCache->get('nexarToken', function (CacheItemInterface $item) {
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
			$item->expiresAfter($resp->expires_in - 60);
			return $resp->access_token;
		});
	}
}
