<?php

namespace Limas\Tests;

use Doctrine\Common\DataFixtures\ReferenceRepository;
use Liip\FunctionalTestBundle\Test\WebTestCase;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Limas\Entity\Part;
use Limas\Entity\PartDistributor;
use Limas\Entity\PartManufacturer;
use Limas\Tests\DataFixtures\DistributorDataLoader;
use Limas\Tests\DataFixtures\ManufacturerDataLoader;
use Limas\Tests\DataFixtures\PartCategoryDataLoader;
use Limas\Tests\DataFixtures\PartDataLoader;
use Limas\Tests\DataFixtures\StorageLocationCategoryDataLoader;
use Limas\Tests\DataFixtures\StorageLocationDataLoader;
use Limas\Tests\DataFixtures\UserDataLoader;
use Nette\Utils\Json;


class DistributorAndManufacturerSearchTest
	extends WebTestCase
{
	protected ReferenceRepository $fixtures;


	protected function setUp(): void
	{
		parent::setUp();
		$this->fixtures = static::getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
			StorageLocationCategoryDataLoader::class,
			StorageLocationDataLoader::class,
			PartCategoryDataLoader::class,
			PartDataLoader::class,
			UserDataLoader::class,
			ManufacturerDataLoader::class,
			DistributorDataLoader::class
		])->getReferenceRepository();
	}

	public function testManufacturerFilter(): void
	{
		$part = $this->fixtures->getReference('part.1');
		$manufacturer = $this->fixtures->getReference('manufacturer.first');

		$part->addManufacturer((new PartManufacturer)
			->setManufacturer($manufacturer)
			->setPartNumber('1')
		);
		$this->getContainer()->get('doctrine.orm.default_entity_manager')->flush();

		$filters = [[
			'property' => 'manufacturers.manufacturer',
			'operator' => '=',
			'value' => $this->getContainer()->get('api_platform.iri_converter')->getIriFromItem($manufacturer),
		]];

		$client = static::makeAuthenticatedClient();

		$client->request(
			'GET',
			$this->getContainer()->get('api_platform.iri_converter')->getIriFromResourceClass(Part::class),
			['filter' => Json::encode($filters)]
		);

		self::assertEquals(200, $client->getResponse()->getStatusCode());

		$data = Json::decode($client->getResponse()->getContent());

		self::assertEquals(1, $data->{'hydra:totalItems'});
	}

	public function testDistributorFilter(): void
	{
		$part = $this->fixtures->getReference('part.1');
		$distributor = $this->fixtures->getReference('distributor.first');

		$partDistributor = new PartDistributor;
		$partDistributor->setDistributor($distributor);

		$part->addDistributor($partDistributor);
		$this->getContainer()->get('doctrine.orm.default_entity_manager')->flush();

		$filters = [[
			'property' => 'distributors.distributor',
			'operator' => '=',
			'value' => $this->getContainer()->get('api_platform.iri_converter')->getIriFromItem($distributor),
		]];

		$client = static::makeAuthenticatedClient();

		$iri = $this->getContainer()->get('api_platform.iri_converter')->getIriFromResourceClass(Part::class);

		$client->request('GET', $iri, ['filter' => Json::encode($filters)]);

		self::assertEquals(200, $client->getResponse()->getStatusCode());

		$data = Json::decode($client->getResponse()->getContent());

		self::assertEquals(1, $data->{'hydra:totalItems'});
	}
}
