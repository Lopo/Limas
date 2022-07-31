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

		$client->request(
			'GET',
			'/api/system_information'
		);

		$response = Json::decode($client->getResponse()->getContent());

		$this->assertIsArray($response);

		$this->assertIsObject($response[0]);
		$this->assertObjectHasAttribute('category', $response[0]);
		$this->assertObjectHasAttribute('name', $response[0]);
		$this->assertObjectHasAttribute('value', $response[0]);
	}

	public function testSystemStatus(): void
	{
		$client = static::makeAuthenticatedClient();

		$client->request(
			'GET',
			'/api/system_status'
		);

		$response = Json::decode($client->getResponse()->getContent());

		$this->assertIsObject($response);
		$this->assertObjectHasAttribute('inactiveCronjobCount', $response);
		$this->assertObjectHasAttribute('inactiveCronjobs', $response);
		$this->assertIsArray($response->inactiveCronjobs);
		$this->assertObjectHasAttribute('schemaStatus', $response);
	}
}
