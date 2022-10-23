<?php

namespace Limas\Tests;

use Doctrine\Common\DataFixtures\ReferenceRepository;
use Liip\FunctionalTestBundle\Test\WebTestCase;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Limas\Entity\StockEntry;
use Limas\Tests\DataFixtures\DistributorDataLoader;
use Limas\Tests\DataFixtures\ManufacturerDataLoader;
use Limas\Tests\DataFixtures\PartCategoryDataLoader;
use Limas\Tests\DataFixtures\PartDataLoader;
use Limas\Tests\DataFixtures\StorageLocationCategoryDataLoader;
use Limas\Tests\DataFixtures\StorageLocationDataLoader;
use Limas\Tests\DataFixtures\UserDataLoader;
use Nette\Utils\Json;


class StockHistoryLostTest
	extends WebTestCase
{
	protected ReferenceRepository $fixtures;


	protected function setUp(): void
	{
		parent::setUp();
		$this->fixtures = static::getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
			UserDataLoader::class,
			StorageLocationCategoryDataLoader::class,
			StorageLocationDataLoader::class,
			PartCategoryDataLoader::class,
			PartDataLoader::class,
			UserDataLoader::class,
			ManufacturerDataLoader::class,
			DistributorDataLoader::class
		])->getReferenceRepository();
	}

	public function testStockHistory(): void
	{
		$em = $this->getContainer()->get('doctrine.orm.default_entity_manager');
		$client = static::makeAuthenticatedClient();

		$part1 = $this->fixtures->getReference('part.1');

		$part1->addStockLevel((new StockEntry)
			->setPart($part1)
			->setStockLevel(5)
			->setUser($this->fixtures->getReference('user.admin'))
		);
		$em->flush();

		$iri = $this->getContainer()->get('api_platform.iri_converter')->getIriFromItem($part1);

		$client->request('GET', $iri);

		$responseObj = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
		$responseObj['stockLevels'] = [];

		$client->request(
			'PUT',
			$iri,
			[],
			[],
			['CONTENT_TYPE' => 'application/json'],
			Json::encode($responseObj)
		);

		self::assertEquals(200, $client->getResponse()->getStatusCode());
		self::assertEquals(1, $em->find($part1::class, $part1->getId())->getStockLevels()->count());
	}
}
