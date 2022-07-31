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

		self::assertObjectHasAttribute('name', $response);
		self::assertObjectHasAttribute('@type', $response);

		self::assertEquals('TipOfTheDay', $response->{'@type'});
		self::assertEquals('FOO', $response->name);

		$client->request('GET', '/api/tip_of_the_day_histories');

		$response = Json::decode($client->getResponse()->getContent());

		self::assertObjectHasAttribute('@type', $response);
		self::assertObjectHasAttribute('hydra:member', $response);

		self::assertEquals('hydra:Collection', $response->{'@type'});

		self::assertArrayHasKey(0, $response->{'hydra:member'});
		self::assertEquals('FOO', $response->{'hydra:member'}[0]->name);

		$client->request(
			'POST',
			'/api/tip_of_the_days/markAllTipsAsUnread'
		);

		self::assertEquals('OK', $client->getResponse()->getContent());

		$client->request(
			'GET',
			'/api/tip_of_the_day_histories'
		);

		$response = Json::decode($client->getResponse()->getContent());

		self::assertObjectHasAttribute('@type', $response);
		self::assertObjectHasAttribute('hydra:member', $response);

		self::assertEquals('hydra:Collection', $response->{'@type'});

		self::assertCount(0, $response->{'hydra:member'});
	}
}
