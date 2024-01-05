<?php

namespace Limas\Tests;

use Doctrine\Common\DataFixtures\ReferenceRepository;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Limas\Entity\PartCategory;
use Limas\Entity\PartMeasurementUnit;
use Limas\Entity\StorageLocation;
use Limas\Tests\DataFixtures\DistributorDataLoader;
use Limas\Tests\DataFixtures\ManufacturerDataLoader;
use Limas\Tests\DataFixtures\PartCategoryDataLoader;
use Limas\Tests\DataFixtures\PartDataLoader;
use Limas\Tests\DataFixtures\StorageLocationCategoryDataLoader;
use Limas\Tests\DataFixtures\StorageLocationDataLoader;
use Limas\Tests\DataFixtures\UserDataLoader;
use Nette\Utils\Json;


class InternalPartNumberTest
	extends WebTestCase
{
	protected ReferenceRepository $fixtures;


	protected function setUp(): void
	{
		parent::setUp();
		$this->fixtures = self::getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
			UserDataLoader::class,
			StorageLocationCategoryDataLoader::class,
			StorageLocationDataLoader::class,
			PartCategoryDataLoader::class,
			PartDataLoader::class,
			ManufacturerDataLoader::class,
			DistributorDataLoader::class
		])->getReferenceRepository();
	}

	public function testInternalPartNumberUniqueness(): void
	{
		$client = $this->makeAuthenticatedClient();

		$content = Json::encode([
			'name' => 'foobar',
			'storageLocation' => '/api/storage_locations/' . $this->fixtures->getReference('storagelocation.first', StorageLocation::class)->getId(),
			'category' => '/api/part_categories/' . $this->fixtures->getReference('partcategory.first', PartCategory::class)->getId(),
			'partUnit' => '/api/part_measurement_units/' . $this->fixtures->getReference('partunit.default', PartMeasurementUnit::class)->getId(),
			'internalPartNumber' => 'foo123'
		]);

		$client->request(
			'POST',
			'/api/parts',
			[],
			[],
			['CONTENT_TYPE' => 'application/json'],
			$content
		);
		$client->request(
			'POST',
			'/api/parts',
			[],
			[],
			['CONTENT_TYPE' => 'application/json'],
			$content
		);

		self::assertEquals(500, $client->getResponse()->getStatusCode());

		$response = Json::decode($client->getResponse()->getContent());

		self::assertObjectHasProperty('@type', $response);
		self::assertObjectHasProperty('@context', $response);
		self::assertObjectHasProperty('hydra:title', $response);
		self::assertObjectHasProperty('hydra:description', $response);

		self::assertEquals('/api/contexts/Error', $response->{'@context'});
		self::assertEquals('hydra:Error', $response->{'@type'});
		self::assertEquals('An error occurred', $response->{'hydra:title'});
		self::assertEquals('The internal part number is already used', $response->{'hydra:description'});
	}
}
