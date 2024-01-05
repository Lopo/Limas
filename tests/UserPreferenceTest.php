<?php

namespace Limas\Tests;

use Doctrine\Common\DataFixtures\ReferenceRepository;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Limas\Tests\DataFixtures\UserDataLoader;
use Nette\Utils\Json;


class UserPreferenceTest
	extends WebTestCase
{
	protected ReferenceRepository $fixtures;


	public function setUp(): void
	{
		$this->fixtures = self::getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
			UserDataLoader::class
		])->getReferenceRepository();
	}

	public function testPreferences(): void
	{
		$client = $this->makeAuthenticatedClient();

		$client->jsonRequest(
			'POST',
			'/api/user_preferences',
			[
				'preferenceKey' => 'foobar',
				'preferenceValue' => '1234',
			]
		);

		$response = Json::decode($client->getResponse()->getContent());

		self::assertIsObject($response, var_export($client->getResponse()->getContent(), true));

		self::assertObjectHasProperty('preferenceKey', $response);
		self::assertObjectHasProperty('preferenceValue', $response);
		self::assertEquals('foobar', $response->preferenceKey);
		self::assertEquals('1234', $response->preferenceValue);

		$client->jsonRequest(
			'GET',
			'/api/user_preferences'
		);

		$response = Json::decode($client->getResponse()->getContent());

		self::assertIsArray($response);

		$preference = $response[0];

		self::assertObjectHasProperty('preferenceKey', $preference);
		self::assertObjectHasProperty('preferenceValue', $preference);
		self::assertEquals('foobar', $preference->preferenceKey);
		self::assertEquals('1234', $preference->preferenceValue);
	}
}
