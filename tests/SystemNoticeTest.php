<?php

namespace Limas\Tests;

use ApiPlatform\Core\Api\IriConverterInterface;
use Liip\FunctionalTestBundle\Test\WebTestCase;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Limas\Service\SystemNoticeService;
use Limas\Tests\DataFixtures\UserDataLoader;
use Nette\Utils\Json;


class SystemNoticeTest
	extends WebTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
			UserDataLoader::class
		])->getReferenceRepository();
	}

	public function testSystemNotices(): void
	{
		$client = static::makeAuthenticatedClient();

		$iri = $this->getContainer()->get(IriConverterInterface::class)->getIriFromItem($this->getContainer()->get(SystemNoticeService::class)->createUniqueSystemNotice('FOO', 'BAR', 'DING'));

		$client->request('GET', $iri);

		$response = Json::decode($client->getResponse()->getContent());

		self::assertEquals('FOO', $response->type);
		self::assertEquals('BAR', $response->title);
		self::assertEquals('DING', $response->description);
		self::assertEquals(false, $response->acknowledged);

		$client->request('PUT', $iri . '/acknowledge');

		$response = Json::decode($client->getResponse()->getContent());
		self::assertEquals(true, $response->acknowledged);
	}
}
