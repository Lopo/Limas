<?php

namespace Limas\Tests;

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

		$iri = $this->getContainer()->get('api_platform.iri_converter')->getIriFromItem($this->getContainer()->get(SystemNoticeService::class)->createUniqueSystemNotice('FOO', 'BAR', 'DING'));
		$ackIri = $iri . '/acknowledge';

		$client->request(
			'GET',
			$iri
		);

		$response = Json::decode($client->getResponse()->getContent());

		$this->assertEquals('FOO', $response->type);
		$this->assertEquals('BAR', $response->title);
		$this->assertEquals('DING', $response->description);
		$this->assertEquals(false, $response->acknowledged);

		$client->request(
			'PUT',
			$ackIri
		);

		$response = Json::decode($client->getResponse()->getContent());
		$this->assertEquals(true, $response->acknowledged);
	}
}
