<?php

namespace Limas\Service;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Nette\Utils\Json;
use Predis\Client as PredisClient;


class OctoPartService
{

	private const OCTOPART_ENDPOINT = 'https://octopart.com/api/v4/endpoint';
	private const OCTOPART_QUERY = <<<'EOD'
    query MyPartSearch($q: String!, $filters: Map, $limit: Int!, $start: Int, $country: String = "DE", $currency: String = "EUR") {
        search(q: $q, filters: $filters, limit: $limit, start: $start, country: $country, currency: $currency) {
          hits
      
          results {
            part {
              id
              mpn
              slug
              short_description
              counts
              manufacturer {
                name
              }
              best_datasheet {
                name
                url
                credit_string
                credit_url
                page_count
                mime_type
              }
              best_image {
                  url
              }
              specs {
                attribute {
                  name
                  group
                }
                display_value
              }
              document_collections {
                name
                documents {
                  name
                  url
                  credit_string
                  credit_url
                }
              }
              descriptions {
                credit_string
                text
              }
              cad {
                add_to_library_url
              }
              reference_designs {
                  name
                  url
              }
              sellers {
                company {
                  homepage_url
                  is_verified
                  name
                  slug
                }
                is_authorized
                is_broker
                is_rfq
                offers {
                  click_url
                  inventory_level
                  moq
                  packaging
                  prices {
                    conversion_rate
                    converted_currency
                    converted_price
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
    query MyPartSearch($id: String!, $country: String = "DE", $currency: String = "EUR") {
        parts(ids: [$id], country: $country, currency: $currency) {
                    id
                    mpn
                    slug
                    short_description
                    counts
                    manufacturer {
                      name
                    }
                    best_datasheet {
                      name
                      url
                      credit_string
                      credit_url
                      page_count
                      mime_type
                    }
                    best_image {
                        url
                    }
                    specs {
                      attribute {
                        name
                        group
                      }
                      display_value
                    }
                    document_collections {
                      name
                      documents {
                        name
                        url
                        credit_string
                        credit_url
                      }
                    }
                    descriptions {
                      credit_string
                      text
                    }
                    cad {
                      add_to_library_url
                    }
                    reference_designs {
                        name
                        url
                    }
                    sellers {
                      company {
                        homepage_url
                        is_verified
                        name
                        slug
                      }
                      is_authorized
                      is_broker
                      is_rfq
                      offers {
                        click_url
                        inventory_level
                        moq
                        packaging
                        prices {
                          conversion_rate
                          converted_currency
                          converted_price
                          currency
                          price
                          quantity
                        }
                        sku
                        updated
                      }
                    }
                }}
EOD;


	public function __construct(
		private readonly string $apiKey,
		private readonly string $limit = '3'
	)
	{
	}

	public function getPartByUID(string $uid): object
	{
		try {
			$redisclient = new PredisClient;
			$redisclient->connect();
			if (null !== ($part = $redisclient->get($uid))) {
				return json_decode($part, true, 512, JSON_THROW_ON_ERROR);
			}
			$redisclient->disconnect();
		} catch (\Exception $e) {
		}

		$body = (new Client)->request('POST', self::OCTOPART_ENDPOINT, [
			RequestOptions::HEADERS => ['Content-Type' => 'application/json'],
			RequestOptions::QUERY => ['token' => $this->apiKey],
			RequestOptions::BODY => Json::encode([
				'query' => self::OCTOPART_PARTQUERY,
				'operationName' => 'MyPartSearch',
				'variables' => [
					'id' => $uid
				]
			])
		])->getBody();

		$data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

		return $data['data']['parts'][0];
	}

	public function getPartyByQuery($q, int $startpage = 1): array
	{
		$body = (new Client)->request('POST', self::OCTOPART_ENDPOINT, [
			RequestOptions::HEADERS => ['Content-Type' => 'application/json'],
			RequestOptions::QUERY => ['token' => $this->apiKey],
			RequestOptions::BODY => Json::encode([
				'query' => self::OCTOPART_QUERY,
				'operationName' => 'MyPartSearch',
				'variables' => [
					'q' => $q,
					'limit' => $this->limit,
					'start' => ($startpage - 1) * (int)$this->limit // 0-based
				]
			])
		])->getBody();

		$parts = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

		// work around the low number of allowed accesses to octopart's api
		try {
			$redisclient = new PredisClient;
			$redisclient->connect();
			$results = $parts['data']['search']['results'];
			foreach ($results as $result) {
				$id = $result['part']['id'];
				$redisclient->set($id, Json::encode($result['part']));
			}
			$redisclient->disconnect();
		} catch (\Exception $e) {
		}

		return $parts;
	}
}
