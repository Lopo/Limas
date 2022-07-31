<?php

namespace Limas\Tests;

use Liip\FunctionalTestBundle\Test\WebTestCase;
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
		$this->getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
			UserDataLoader::class
		])->getReferenceRepository();
	}

	public function testSystemPreferenceService(): void
	{
		$this->getContainer()->get(SystemPreferenceService::class)->setSystemPreference('foo', 'bar');

		self::assertEquals('bar', $this->getContainer()->get(SystemPreferenceService::class)->getSystemPreferenceValue('foo'));

		$this->getContainer()->get(SystemPreferenceService::class)->setSystemPreference('foo', 'bar2');

		self::assertEquals('bar2', $this->getContainer()->get(SystemPreferenceService::class)->getSystemPreferenceValue('foo'));

		$preference = $this->getContainer()->get(SystemPreferenceService::class)->getPreference('foo');
		self::assertEquals('foo', $preference->getPreferenceKey());

		$this->expectException(SystemPreferenceNotFoundException::class);
		self::assertEquals('bar2', $this->getContainer()->get(SystemPreferenceService::class)->getSystemPreferenceValue('foo2'));
	}

	public function testSystemPreferenceCreate(): void
	{
		$client = static::makeAuthenticatedClient();

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
