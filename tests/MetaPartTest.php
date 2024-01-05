<?php

namespace Limas\Tests;

use Doctrine\Common\DataFixtures\ReferenceRepository;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Limas\Entity\Part;
use Limas\Service\PartService;
use Limas\Tests\DataFixtures\DistributorDataLoader;
use Limas\Tests\DataFixtures\ManufacturerDataLoader;
use Limas\Tests\DataFixtures\PartCategoryDataLoader;
use Limas\Tests\DataFixtures\PartDataLoader;
use Limas\Tests\DataFixtures\StorageLocationCategoryDataLoader;
use Limas\Tests\DataFixtures\StorageLocationDataLoader;
use Limas\Tests\DataFixtures\UserDataLoader;


class MetaPartTest
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

	public function testMetaPartMatching(): void
	{
		$container = self::getContainer();

		$metaPart1 = $this->fixtures->getReference('metapart.1', Part::class);
		$metaSourcePart1 = $this->fixtures->getReference('metapart.source.1', Part::class);
		$metaSourcePart2 = $this->fixtures->getReference('metapart.source.2', Part::class);
		$metaSourcePart3 = $this->fixtures->getReference('metapart.source.3', Part::class);

		$matches = $container->get(PartService::class)->getMatchingMetaParts($metaPart1);

		self::assertContains($metaSourcePart1, $matches);
		self::assertContains($metaSourcePart2, $matches);
		self::assertNotContains($metaSourcePart3, $matches);

		$metaPart2 = $this->fixtures->getReference('metapart.2', Part::class);

		$matches2 = $container->get(PartService::class)->getMatchingMetaParts($metaPart2);

		self::assertNotContains($metaSourcePart1, $matches2);
		self::assertContains($metaSourcePart2, $matches2);
		self::assertNotContains($metaSourcePart3, $matches2);
	}
}
