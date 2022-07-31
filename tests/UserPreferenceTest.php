<?php

namespace Limas\Tests;

use Doctrine\Common\DataFixtures\ReferenceRepository;
use Liip\FunctionalTestBundle\Test\WebTestCase;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Limas\Tests\DataFixtures\UserDataLoader;
use Nette\Utils\Json;


class UserPreferenceTest
	extends WebTestCase
{
	protected ReferenceRepository $fixtures;


	public function setUp(): void
	{
		$this->fixtures = $this->getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
			UserDataLoader::class
		])->getReferenceRepository();
	}

	public function testPreferences(): void
	{
		$client = static::makeAuthenticatedClient();

		$client->jsonRequest(
			'POST',
			'/api/user_preferences',
			[
				'preferenceKey' => 'foobar',
				'preferenceValue' => '1234',
			]
		);

		$response = Json::decode($client->getResponse()->getContent());

		$this->assertIsObject($response, var_export($client->getResponse()->getContent(), true));

		$this->assertObjectHasAttribute('preferenceKey', $response);
		$this->assertObjectHasAttribute('preferenceValue', $response);
		$this->assertEquals('foobar', $response->preferenceKey);
		$this->assertEquals('1234', $response->preferenceValue);

		$client->jsonRequest(
			'GET',
			'/api/user_preferences'
		);

		$response = Json::decode($client->getResponse()->getContent());

		$this->assertIsArray($response);

		$preference = $response[0];

		$this->assertObjectHasAttribute('preferenceKey', $preference);
		$this->assertObjectHasAttribute('preferenceValue', $preference);
		$this->assertEquals('foobar', $preference->preferenceKey);
		$this->assertEquals('1234', $preference->preferenceValue);
	}
}
