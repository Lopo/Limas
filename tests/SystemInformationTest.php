<?php

namespace Limas\Tests;

use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Limas\Tests\DataFixtures\UserDataLoader;
use Nette\Utils\Json;


class SystemInformationTest
	extends WebTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		self::getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
			UserDataLoader::class
		])->getReferenceRepository();
	}

	public function testSystemInformation(): void
	{
		$client = $this->makeAuthenticatedClient();

		$client->request('GET', '/api/system_information');

		$response = Json::decode($client->getResponse()->getContent());

		self::assertIsArray($response);

		self::assertIsObject($response[0]);
		self::assertObjectHasProperty('category', $response[0]);
		self::assertObjectHasProperty('name', $response[0]);
		self::assertObjectHasProperty('value', $response[0]);
	}

	public function testSystemStatus(): void
	{
		$client = $this->makeAuthenticatedClient();

		$client->request('GET', '/api/system_status');

		$response = Json::decode($client->getResponse()->getContent());

		self::assertIsObject($response);
		self::assertObjectHasProperty('inactiveCronjobCount', $response);
		self::assertObjectHasProperty('inactiveCronjobs', $response);
		self::assertIsArray($response->inactiveCronjobs);
		self::assertObjectHasProperty('schemaStatus', $response);
	}
}
