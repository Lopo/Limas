<?php

namespace Limas\Tests;

use Doctrine\Common\DataFixtures\ReferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Limas\Entity\Distributor;
use Limas\Entity\Manufacturer;
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
		$this->fixtures = self::getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
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
		$part = $this->fixtures->getReference('part.1', Part::class);
		$manufacturer = $this->fixtures->getReference('manufacturer.first', Manufacturer::class);

		$part->addManufacturer((new PartManufacturer)
			->setManufacturer($manufacturer)
			->setPartNumber('1')
		);
		self::getContainer()->get(EntityManagerInterface::class)->flush();

		$client = $this->makeAuthenticatedClient();

		$client->request(
			'GET',
			'/api/parts',
			['filter' => Json::encode([[
				'property' => 'manufacturers.manufacturer',
				'operator' => '=',
				'value' => '/api/manufacturers/' . $manufacturer->getId()
			]])]
		);

		self::assertEquals(200, $client->getResponse()->getStatusCode());

		$data = Json::decode($client->getResponse()->getContent());

		self::assertEquals(1, $data->{'hydra:totalItems'});
	}

	public function testDistributorFilter(): void
	{
		$part = $this->fixtures->getReference('part.1', Part::class);
		$distributor = $this->fixtures->getReference('distributor.first', Distributor::class);

		$partDistributor = new PartDistributor;
		$partDistributor->setDistributor($distributor);

		$part->addDistributor($partDistributor);
		self::getContainer()->get(EntityManagerInterface::class)->flush();

		$filters = [[
			'property' => 'distributors.distributor',
			'operator' => '=',
			'value' => '/api/distributors/' . $distributor->getId()
		]];

		$client = $this->makeAuthenticatedClient();

		$client->request('GET', '/api/parts', ['filter' => Json::encode($filters)]);

		self::assertEquals(200, $client->getResponse()->getStatusCode());

		$data = Json::decode($client->getResponse()->getContent());

		self::assertEquals(1, $data->{'hydra:totalItems'});
	}
}
