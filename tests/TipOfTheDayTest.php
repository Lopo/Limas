<?php

namespace Limas\Tests;

use Doctrine\Common\DataFixtures\ReferenceRepository;
use Liip\FunctionalTestBundle\Test\WebTestCase;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Limas\Tests\DataFixtures\TipOfTheDayDataLoader;
use Limas\Tests\DataFixtures\UserDataLoader;
use Nette\Utils\Json;


class TipOfTheDayTest
	extends WebTestCase
{
	protected ReferenceRepository $fixtures;


	protected function setUp(): void
	{
		parent::setUp();
		$this->fixtures = $this->getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
			TipOfTheDayDataLoader::class,
			UserDataLoader::class
		])->getReferenceRepository();
	}

	public function testTips(): void
	{
		$client = static::makeAuthenticatedClient();

		$tip = $this->fixtures->getReference('tipoftheday');

		$client->request(
			'PUT',
			$this->getContainer()->get('api_platform.iri_converter')->getIriFromItem($tip) . '/markTipRead'
		);

		$response = Json::decode($client->getResponse()->getContent());

		$this->assertObjectHasAttribute('name', $response);
		$this->assertObjectHasAttribute('@type', $response);

		$this->assertEquals('TipOfTheDay', $response->{'@type'});
		$this->assertEquals('FOO', $response->name);

		$client->request('GET', '/api/tip_of_the_day_histories');

		$response = Json::decode($client->getResponse()->getContent());

		$this->assertObjectHasAttribute('@type', $response);
		$this->assertObjectHasAttribute('hydra:member', $response);

		$this->assertEquals('hydra:Collection', $response->{'@type'});

		$this->assertArrayHasKey(0, $response->{'hydra:member'});
		$this->assertEquals('FOO', $response->{'hydra:member'}[0]->name);

		$client->request(
			'POST',
			'/api/tip_of_the_days/markAllTipsAsUnread'
		);

		$this->assertEquals('OK', $client->getResponse()->getContent());

		$client->request(
			'GET',
			'/api/tip_of_the_day_histories'
		);

		$response = Json::decode($client->getResponse()->getContent());

		$this->assertObjectHasAttribute('@type', $response);
		$this->assertObjectHasAttribute('hydra:member', $response);

		$this->assertEquals('hydra:Collection', $response->{'@type'});

		$this->assertCount(0, $response->{'hydra:member'});
	}
}
