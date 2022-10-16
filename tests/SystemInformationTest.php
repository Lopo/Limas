<?php

namespace Limas\Tests;

use Liip\FunctionalTestBundle\Test\WebTestCase;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Limas\Tests\DataFixtures\UserDataLoader;
use Nette\Utils\Json;


class SystemInformationTest
	extends WebTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
			UserDataLoader::class
		])->getReferenceRepository();
	}

	public function testSystemInformation(): void
	{
		$client = static::makeAuthenticatedClient();

		$client->request('GET', '/api/system_information');

		$response = Json::decode($client->getResponse()->getContent());

		self::assertIsArray($response);

		self::assertIsObject($response[0]);
		self::assertObjectHasAttribute('category', $response[0]);
		self::assertObjectHasAttribute('name', $response[0]);
		self::assertObjectHasAttribute('value', $response[0]);
	}

	public function testSystemStatus(): void
	{
		$client = static::makeAuthenticatedClient();

		$client->request('GET', '/api/system_status');

		$response = Json::decode($client->getResponse()->getContent());

		self::assertIsObject($response);
		self::assertObjectHasAttribute('inactiveCronjobCount', $response);
		self::assertObjectHasAttribute('inactiveCronjobs', $response);
		self::assertIsArray($response->inactiveCronjobs);
		self::assertObjectHasAttribute('schemaStatus', $response);
	}
}
