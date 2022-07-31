<?php

namespace Limas\Tests;

use Doctrine\Common\DataFixtures\ReferenceRepository;
use Liip\FunctionalTestBundle\Test\WebTestCase;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
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

	public function testMetaPartMatching(): void
	{
		$metaPart1 = $this->fixtures->getReference('metapart.1');
		$metaSourcePart1 = $this->fixtures->getReference('metapart.source.1');
		$metaSourcePart2 = $this->fixtures->getReference('metapart.source.2');
		$metaSourcePart3 = $this->fixtures->getReference('metapart.source.3');

		$matches = $this->getContainer()->get(PartService::class)->getMatchingMetaParts($metaPart1);

		$this->assertContains($metaSourcePart1, $matches);
		$this->assertContains($metaSourcePart2, $matches);
		$this->assertNotContains($metaSourcePart3, $matches);

		$metaPart2 = $this->fixtures->getReference('metapart.2');

		$matches2 = $this->getContainer()->get(PartService::class)->getMatchingMetaParts($metaPart2);

		$this->assertNotContains($metaSourcePart1, $matches2);
		$this->assertContains($metaSourcePart2, $matches2);
		$this->assertNotContains($metaSourcePart3, $matches2);
	}
}
