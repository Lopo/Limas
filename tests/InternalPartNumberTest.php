<?php

namespace Limas\Tests;

use Doctrine\Common\DataFixtures\ReferenceRepository;
use Liip\FunctionalTestBundle\Test\WebTestCase;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
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
		$this->fixtures = $this->getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
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
		$client = static::makeAuthenticatedClient();

		$iriConverter = $this->getContainer()->get('api_platform.iri_converter');

		$content = Json::encode([
			'name' => 'foobar',
			'storageLocation' => $iriConverter->getIriFromItem($this->fixtures->getReference('storagelocation.first')),
			'category' => $iriConverter->getIriFromItem($this->fixtures->getReference('partcategory.first')),
			'partUnit' => $iriConverter->getIriFromItem($this->fixtures->getReference('partunit.default')),
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

		self::assertObjectHasAttribute('@type', $response);
		self::assertObjectHasAttribute('@context', $response);
		self::assertObjectHasAttribute('hydra:title', $response);
		self::assertObjectHasAttribute('hydra:description', $response);

		self::assertEquals('/api/contexts/Error', $response->{'@context'});
		self::assertEquals('hydra:Error', $response->{'@type'});
		self::assertEquals('An error occurred', $response->{'hydra:title'});
		self::assertEquals('The internal part number is already used', $response->{'hydra:description'});
	}
}
