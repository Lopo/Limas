<?php

namespace Limas\Tests;

use Doctrine\Common\DataFixtures\ReferenceRepository;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Limas\Entity\Part;
use Limas\Entity\StorageLocation;
use Limas\Tests\DataFixtures\DistributorDataLoader;
use Limas\Tests\DataFixtures\ManufacturerDataLoader;
use Limas\Tests\DataFixtures\PartCategoryDataLoader;
use Limas\Tests\DataFixtures\PartDataLoader;
use Limas\Tests\DataFixtures\StorageLocationCategoryDataLoader;
use Limas\Tests\DataFixtures\StorageLocationDataLoader;
use Limas\Tests\DataFixtures\UserDataLoader;
use Nette\Utils\Json;


class AdvancedSearchFilterTest
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

	public function testEqualFilter(): void
	{
		$client = $this->makeAuthenticatedClient();

		$client->request(
			'GET',
			'/api/parts?filter=' . Json::encode([
				[
					'property' => 'storageLocation.name',
					'operator' => '=',
					'value' => 'test'
				]
			]),
			[],
			[],
			['CONTENT_TYPE' => 'application/json']
		);
		self::assertResponseIsSuccessful();

		$data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

		self::assertArrayHasKey('hydra:member', $data);
		self::assertCount(1, $data['hydra:member']);
		self::assertArrayHasKey('@id', $data['hydra:member'][0]);

		self::assertEquals(
			'/api/parts/' . $this->fixtures->getReference('part.1', Part::class)->getId(),
			$data['hydra:member'][0]['@id']
		);
	}

	public function testEqualFilterSame(): void
	{
		$client = $this->makeAuthenticatedClient();

		$client->request(
			'GET',
			'/api/parts?filter=' . Json::encode([
				[
					'property' => 'name',
					'operator' => '=',
					'value' => 'FOOBAR'
				]
			]),
			[],
			[],
			['CONTENT_TYPE' => 'application/json']
		);

		$data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

		self::assertArrayHasKey('hydra:member', $data);
		self::assertCount(1, $data['hydra:member']);
		self::assertArrayHasKey('@id', $data['hydra:member'][0]);

		self::assertEquals(
			'/api/parts/' . $this->fixtures->getReference('part.1', Part::class)->getId(),
			$data['hydra:member'][0]['@id']
		);
	}

	public function testIDReference(): void
	{
		$client = $this->makeAuthenticatedClient();

		$client->request(
			'GET',
			'/api/parts?filter=' . Json::encode([
				[
					'property' => 'storageLocation',
					'operator' => '=',
					'value' => '/api/storage_locations/' . $this->fixtures->getReference('storagelocation.first', StorageLocation::class)->getId()
				]
			]),
			[],
			[],
			['CONTENT_TYPE' => 'application/json']
		);

		$data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

		self::assertArrayHasKey('hydra:member', $data);
		self::assertCount(1, $data['hydra:member']);
	}

	public function testIDReferenceArray(): void
	{
		$client = $this->makeAuthenticatedClient();

		$client->request(
			'GET',
			'/api/parts?filter=' . Json::encode([
				[
					'property' => 'storageLocation',
					'operator' => 'IN',
					'value' => [
						'/api/storage_locations/' . $this->fixtures->getReference('storagelocation.first', StorageLocation::class)->getId(),
						'/api/storage_locations/' . $this->fixtures->getReference('storagelocation.second', StorageLocation::class)->getId()
					]
				]
			]),
			[],
			[],
			['CONTENT_TYPE' => 'application/json']
		);

		$data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

		self::assertArrayHasKey('hydra:member', $data);
		self::assertGreaterThan(1, $data['hydra:member']);
	}

	public function testLikeFilter(): void
	{
		$client = $this->makeAuthenticatedClient();

		$client->request(
			'GET',
			'/api/parts?filter=' . Json::encode([
				[
					'property' => 'storageLocation.name',
					'operator' => 'LIKE',
					'value' => '%test%'
				]
			]),
			[],
			[],
			['CONTENT_TYPE' => 'application/json']
		);

		$data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

		self::assertArrayHasKey('hydra:member', $data);
		self::assertGreaterThanOrEqual(2, $data['hydra:member']);
	}

	public function testSorter(): void
	{
		$client = $this->makeAuthenticatedClient();

		$client->request(
			'GET',
			'/api/parts?order=' . Json::encode([
				[
					'property' => 'storageLocation.name',
					'direction' => 'ASC'
				]
			]),
			[],
			[],
			['CONTENT_TYPE' => 'application/json']
		);

		$data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

		self::assertArrayHasKey('hydra:member', $data);
		self::assertGreaterThanOrEqual(2, $data['hydra:member']);
	}

	public function testOrFilterJoin(): void
	{
		$client = $this->makeAuthenticatedClient();

		$client->request(
			'GET',
			'/api/parts?filter=' . Json::encode([
				[
					'type' => 'OR',
					'subfilters' => [
						[
							'property' => 'storageLocation.name',
							'operator' => '=',
							'value' => 'test'
						],
						[
							'property' => 'storageLocation.name',
							'operator' => '=',
							'value' => 'test2'
						]
					]
				]
			]),
			[],
			[],
			['CONTENT_TYPE' => 'application/json']
		);

		$data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

		self::assertArrayHasKey('hydra:member', $data);
		self::assertGreaterThanOrEqual(2, $data['hydra:member']);
	}

	public function testOrFilter(): void
	{
		$client = $this->makeAuthenticatedClient();

		$client->request(
			'GET',
			'/api/parts?filter=' . Json::encode([
				[
					'type' => 'OR',
					'subfilters' => [
						[
							'property' => 'name',
							'operator' => '=',
							'value' => 'FOOBAR'
						],
						[
							'property' => 'name',
							'operator' => '=',
							'value' => 'FOOBAR2'
						]
					]
				]
			]),
			[],
			[],
			['CONTENT_TYPE' => 'application/json']
		);

		$data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

		self::assertArrayHasKey('hydra:member', $data);
		self::assertCount(2, $data['hydra:member']);
	}

//	protected function tearDown(): void
//	{
//		parent::tearDown();
//		unset($this->dbTool);
//	}
}
