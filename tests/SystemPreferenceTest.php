<?php

namespace Limas\Tests;

use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Limas\Exceptions\SystemPreferenceNotFoundException;
use Limas\Service\SystemPreferenceService;
use Limas\Tests\DataFixtures\UserDataLoader;
use Nette\Utils\Json;


class SystemPreferenceTest
	extends WebTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		self::getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
			UserDataLoader::class
		])->getReferenceRepository();
	}

	public function testSystemPreferenceService(): void
	{
		$container = self::getContainer();

		$container->get(SystemPreferenceService::class)->setSystemPreference('foo', 'bar');

		self::assertEquals('bar', $container->get(SystemPreferenceService::class)->getSystemPreferenceValue('foo'));

		$container->get(SystemPreferenceService::class)->setSystemPreference('foo', 'bar2');

		self::assertEquals('bar2', $container->get(SystemPreferenceService::class)->getSystemPreferenceValue('foo'));

		$preference = $container->get(SystemPreferenceService::class)->getPreference('foo');
		self::assertEquals('foo', $preference->getPreferenceKey());

		$this->expectException(SystemPreferenceNotFoundException::class);
		self::assertEquals('bar2', $container->get(SystemPreferenceService::class)->getSystemPreferenceValue('foo2'));
	}

	public function testSystemPreferenceCreate(): void
	{
		$client = $this->makeAuthenticatedClient();

		// First test: Ensure invalid auth key is returned
		$client->request(
			'POST',
			'/api/system_preferences',
			[],
			[],
			[],
			Json::encode([
				'preferenceKey' => 'foobar',
				'@type' => 'SystemPreference',
				'preferenceValue' => 'test',
			])
		);

		self::assertEquals(200, $client->getResponse()->getStatusCode());
	}
}
