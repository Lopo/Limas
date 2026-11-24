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

use Doctrine\ORM\EntityManagerInterface;
use Limas\Entity\Distributor;
use Limas\Entity\Manufacturer;
use Limas\Entity\PartCategory;
use Limas\Entity\PartDistributor;
use Limas\Entity\PartManufacturer;
use Limas\Entity\PartMeasurementUnit;


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

	/**
	 * Added by me and Sparky 11/24/26
	 */
	public function testOrFilterWithOptionalAssociations(): void
{
    $entityManager = self::getContainer()->get(EntityManagerInterface::class);

    $category = $this->fixtures->getReference(
        'partcategory.first',
        PartCategory::class
    );

    $storageLocation = $this->fixtures->getReference(
        'storagelocation.first',
        StorageLocation::class
    );

    $partUnit = $this->fixtures->getReference(
        'partunit.default',
        PartMeasurementUnit::class
    );

    $manufacturer = $this->fixtures->getReference(
        'manufacturer.first',
        Manufacturer::class
    );

    $distributor = $this->fixtures->getReference(
        'distributor.first',
        Distributor::class
    );

    $testParts = [
        ['M6 Hex Bolt', null, null, true],
        ['Brass Insert', 'INSERT-M6', null, true],
        ['Steel Washer', null, 'WASHER-M6', true],
        ['M6 Socket Screw', 'SCREW-001', 'SKU-001', true],
        ['M8 Hex Bolt', 'BOLT-008', 'SKU-008', false],
        ['M4 Hex Nut', null, null, false],
    ];

    $expectedParts = [];

    foreach ($testParts as [$name, $partNumber, $sku, $matches]) {
        $part = (new Part)
            ->setName($name)
            ->setCategory($category)
            ->setStorageLocation($storageLocation)
            ->setPartUnit($partUnit);

        if ($partNumber !== null) {
            $partManufacturer = (new PartManufacturer)
                ->setManufacturer($manufacturer)
                ->setPartNumber($partNumber);

            $part->addManufacturer($partManufacturer);
            $entityManager->persist($partManufacturer);
        }

        if ($sku !== null) {
            $partDistributor = (new PartDistributor)
                ->setDistributor($distributor)
                ->setSku($sku);

            $part->addDistributor($partDistributor);
            $entityManager->persist($partDistributor);
        }

        $entityManager->persist($part);

        if ($matches) {
            $expectedParts[] = $part;
        }
    }

    $entityManager->flush();

    $expectedIds = array_map(
        static fn (Part $part): string => '/api/parts/' . $part->getId(),
        $expectedParts
    );

    $properties = [
        'name',
        'description',
        'comment',
        'internalPartNumber',
        'distributors.sku',
        'distributors.orderNumber',
        'manufacturers.partNumber',
    ];

    $subfilters = array_map(
        static fn (string $property): array => [
            'property' => $property,
            'operator' => 'like',
            'value' => '%M6%',
        ],
        $properties
    );

    $filters = [
        [
            'type' => 'AND',
            'subfilters' => [
                [
                    'type' => 'OR',
                    'subfilters' => $subfilters,
                ],
            ],
        ],
    ];

    $client = $this->makeAuthenticatedClient();

    $client->request(
        'GET',
        '/api/parts',
        [
            'filter' => Json::encode($filters),
            'itemsPerPage' => 50,
        ],
        [],
        ['HTTP_ACCEPT' => 'application/ld+json']
    );

    self::assertResponseIsSuccessful();

    $data = json_decode(
        $client->getResponse()->getContent(),
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    self::assertArrayHasKey('hydra:member', $data);
    self::assertArrayHasKey('hydra:totalItems', $data);

    self::assertEquals(4, $data['hydra:totalItems']);

    $actualIds = array_column($data['hydra:member'], '@id');

    self::assertEqualsCanonicalizing($expectedIds, $actualIds);
}
	/**
	 * A filter path that isn't a real field/association on the entity, or is
	 * nested too deeply, must be rejected with a clean 400 — not leak a DQL
	 * parse error (existence oracle) or force a huge JOIN chain
	 */
	public function testUnknownOrTooDeepPropertyReturnsBadRequest(): void
	{
		$client = $this->makeAuthenticatedClient();

		$cases = [
			[['property' => 'totallyBogusColumn', 'operator' => '=', 'value' => 'x']],
			[['property' => 'bogusRelation.name', 'operator' => '=', 'value' => 'x']],
			[['property' => 'a.b.c.d.e.name', 'operator' => '=', 'value' => 'x']]
		];

		foreach ($cases as $filter) {
			$client->request(
				'GET',
				'/api/parts?filter=' . Json::encode($filter),
				[],
				[],
				['CONTENT_TYPE' => 'application/json']
			);
			self::assertEquals(
				400,
				$client->getResponse()->getStatusCode(),
				Json::encode($filter)
			);
		}
	}

//	protected function tearDown(): void
//	{
//		parent::tearDown();
//		unset($this->dbTool);
//	}
}
