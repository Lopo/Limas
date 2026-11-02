<?php

namespace Limas\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Limas\Entity\Manufacturer;
use Limas\Entity\ManufacturerAlias;
use Limas\Service\Integration\InfoProvider\AggregatorImporter;
use Limas\Service\ManufacturerCanonicalizer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


/**
 * Regression guard for the backend import path creating a duplicate
 * Manufacturer (1062 on the unique name index) when canonicalize() misses a
 * manufacturer that already exists: either its alias is still unassigned, or
 * its stored name keeps a corporate suffix the normalised key drops.
 */
class AggregatorImporterManufacturerTest
	extends KernelTestCase
{
	private EntityManagerInterface $em;


	protected function setUp(): void
	{
		parent::setUp();
		self::getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([]);
		$this->em = self::getContainer()->get(EntityManagerInterface::class);
	}

	private function resolve(string $rawName): Manufacturer
	{
		$importer = self::getContainer()->get(AggregatorImporter::class);
		$method = new \ReflectionMethod($importer, 'resolveManufacturer');
		return $method->invoke($importer, $rawName);
	}

	private function createManufacturer(string $name): Manufacturer
	{
		$m = (new Manufacturer)->setName($name);
		$this->em->persist($m);
		$this->em->flush();
		return $m;
	}

	/**
	 * Unassigned alias (left behind by a search) shadows an existing
	 * manufacturer created through the editor — must reuse it, not insert
	 */
	public function testReusesManufacturerShadowedByUnassignedAlias(): void
	{
		$existing = $this->createManufacturer('YAGEO');
		$this->em->persist(new ManufacturerAlias('YAGEO', ManufacturerCanonicalizer::normalize('YAGEO')));
		$this->em->flush();

		$resolved = $this->resolve('YAGEO');

		self::assertSame($existing->getId(), $resolved->getId());
		$alias = $this->em->getRepository(ManufacturerAlias::class)->findOneBy(['aliasNormalized' => 'yageo']);
		self::assertSame($existing->getId(), $alias?->getManufacturer()?->getId());
		self::assertTrue($alias->isVerified());
	}

	/**
	 * Stored name keeps the corporate suffix ("Bourns Inc.") while the
	 * canonical key strips it ("bourns"), so the direct-name match misses
	 */
	public function testReusesManufacturerWithCorporateSuffix(): void
	{
		$existing = $this->createManufacturer('Bourns Inc.');

		$resolved = $this->resolve('Bourns Inc.');

		self::assertSame($existing->getId(), $resolved->getId());
		self::assertCount(1, $this->em->getRepository(Manufacturer::class)->findAll());
	}

	public function testCreatesGenuinelyNewManufacturer(): void
	{
		$resolved = $this->resolve('Newco Electronics');

		self::assertNotNull($resolved->getId());
		self::assertSame('Newco Electronics', $resolved->getName());
	}
}
