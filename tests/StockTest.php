<?php

namespace Limas\Tests;

use Doctrine\Common\DataFixtures\ReferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Limas\Entity\Part;
use Limas\Tests\DataFixtures\PartCategoryDataLoader;
use Limas\Tests\DataFixtures\PartDataLoader;
use Limas\Tests\DataFixtures\StorageLocationCategoryDataLoader;
use Limas\Tests\DataFixtures\StorageLocationDataLoader;
use Limas\Tests\DataFixtures\UserDataLoader;
use Nette\Utils\Json;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;


class StockTest
	extends WebTestCase
{
	protected ReferenceRepository $fixtures;
	protected KernelBrowser $testClient;


	protected function setUp(): void
	{
		parent::setUp();
		$this->fixtures = self::getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
			UserDataLoader::class,
			StorageLocationCategoryDataLoader::class,
			StorageLocationDataLoader::class,
			PartCategoryDataLoader::class,
			PartDataLoader::class
		])->getReferenceRepository();
	}

	private function getStockLevel(Part $part)
	{
		$qb = self::getContainer()->get(EntityManagerInterface::class)->createQueryBuilder();
		return $qb->select('p.stockLevel')
			->from(Part::class, 'p')
			->where($qb->expr()->eq('p.id', ':id'))
			->setParameter('id', $part->getId())
			->getQuery()->getSingleScalarResult();
	}

	public function testAddStock(): void
	{
		$client = $this->makeAuthenticatedClient();

		$part = $this->fixtures->getReference('part.1', Part::class);
		$oldStockLevel = $this->getStockLevel($part);

		$client->request(
			'PUT',
			'/api/parts/' . $part->getId() . '/addStock',
			['quantity' => 5],
			[],
//			['CONTENT_TYPE' => 'application/json'],
			['CONTENT_TYPE' => 'application/x-www-form-urlencoded']
//			Json::encode(['quantity' => 5])
		);

		$result = Json::decode($client->getResponse()->getContent());
		$newStockLevel = $this->getStockLevel($part);

		self::assertEquals($oldStockLevel + 5, $newStockLevel);
		self::assertObjectHasProperty('stockLevel', $result);
		self::assertEquals($newStockLevel, $result->stockLevel);
	}

	public function testRemoveStock(): void
	{
		$client = $this->makeAuthenticatedClient();

		$part = $this->fixtures->getReference('part.1', Part::class);
		$oldStockLevel = $this->getStockLevel($part);

		$client->request(
			'PUT',
			'/api/parts/' . $part->getId() . '/removeStock',
			['quantity' => 7],
			[],
//			['CONTENT_TYPE' => 'application/json'],
			['CONTENT_TYPE' => 'application/x-www-form-urlencoded']
//			Json::encode(['quantity' => 7])
		);

		$result = Json::decode($client->getResponse()->getContent());
		$newStockLevel = $this->getStockLevel($part);

		self::assertEquals($oldStockLevel - 7, $newStockLevel);
		self::assertObjectHasProperty('stockLevel', $result);
		self::assertEquals($newStockLevel, $result->stockLevel);
	}

	public function testSetStock(): void
	{
		$client = $this->makeAuthenticatedClient();

		$part = $this->fixtures->getReference('part.1', Part::class);

		$client->request(
			'PUT',
			'/api/parts/' . $part->getId() . '/setStock',
			['quantity' => 33],
			[],
//			['CONTENT_TYPE' => 'application/json'],
			['CONTENT_TYPE' => 'application/x-www-form-urlencoded']
//			Json::encode(['quantity' => 33])
		);

		$result = Json::decode($client->getResponse()->getContent());
		$newStockLevel = $this->getStockLevel($part);

		self::assertEquals(33, $newStockLevel);
		self::assertObjectHasProperty('stockLevel', $result);
		self::assertEquals($newStockLevel, $result->stockLevel);
	}
}
