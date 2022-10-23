<?php

namespace Limas\Tests;

use Liip\FunctionalTestBundle\Test\WebTestCase;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Limas\Service\VersionService;
use Limas\Tests\DataFixtures\UserDataLoader;
use Nette\Utils\Json;


class VersionServiceTest
	extends WebTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
			UserDataLoader::class
		])->getReferenceRepository();
	}

	public function testVersionService(): void
	{
		$versionService = $this->getContainer()->get(VersionService::class);

		$versionService->setVersion('0.1.8');
		$versionService->setVersionURI(__DIR__ . '/DataFixtures/files/versions.json');

		$versionService->doVersionCheck();

		$client = static::makeAuthenticatedClient();

		$client->request(
			'GET',
			'/api/system_notices'
		);

		$response = Json::decode($client->getResponse()->getContent());

		self::assertObjectHasAttribute('hydra:member', $response);
		self::assertCount(1, $response->{'hydra:member'});

		$versionEntry = $response->{'hydra:member'}[0];

		self::assertEquals('New Limas Version 0.1.9 available', $versionEntry->title);
	}
}
