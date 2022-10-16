<?php

namespace Limas\Tests;

use Doctrine\Common\DataFixtures\ReferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Liip\FunctionalTestBundle\Test\WebTestCase;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Limas\Entity\Part;
use Limas\Entity\PartAttachment;
use Limas\Entity\PartDistributor;
use Limas\Entity\PartManufacturer;
use Limas\Exceptions\CategoryNotAssignedException;
use Limas\Exceptions\StorageLocationNotAssignedException;
use Limas\Service\UploadedFileService;
use Limas\Tests\DataFixtures\DistributorDataLoader;
use Limas\Tests\DataFixtures\ManufacturerDataLoader;
use Limas\Tests\DataFixtures\PartCategoryDataLoader;
use Limas\Tests\DataFixtures\PartDataLoader;
use Limas\Tests\DataFixtures\StorageLocationCategoryDataLoader;
use Limas\Tests\DataFixtures\StorageLocationDataLoader;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;


class PartTest
	extends WebTestCase
{
	protected ReferenceRepository $fixtures;
	protected KernelBrowser $testClient;


	protected function setUp(): void
	{
		parent::setUp();
		$this->fixtures = static::getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
			StorageLocationCategoryDataLoader::class,
			StorageLocationDataLoader::class,
			PartCategoryDataLoader::class,
			PartDataLoader::class,
			ManufacturerDataLoader::class,
			DistributorDataLoader::class
		])->getReferenceRepository();
	}

	public function testCategoryRequired(): void
	{
		$this->expectException(CategoryNotAssignedException::class);
		$container = self::getContainer();
		$part = (new Part)
			->setName('TEST')
			->setStorageLocation($this->fixtures->getReference('storagelocation.first'));
		$container->get(EntityManagerInterface::class)->persist($part);
		$container->get(EntityManagerInterface::class)->flush($part);
	}

	public function testStorageLocationRequired(): void
	{
		$this->expectException(StorageLocationNotAssignedException::class);
		$container = self::getContainer();
		$part = (new Part)
			->setName('TEST')
			->setCategory($this->fixtures->getReference('partcategory.root'));

		$container->get(EntityManagerInterface::class)->persist($part);
		$container->get(EntityManagerInterface::class)->flush($part);
	}

	public function testBasics(): void
	{
		$container = self::getContainer();
		$part = (new Part)
			->setName('TEST')
			->setCategory($this->fixtures->getReference('partcategory.root'))
			->setStorageLocation($this->fixtures->getReference('storagelocation.first'));

		$container->get(EntityManagerInterface::class)->persist($part);
		$container->get(EntityManagerInterface::class)->flush($part);
	}

	public function testAssociationRemoval(): void
	{
		$container = self::getContainer();
		$part = (new Part)
			->setName('TEST')
			->setCategory($this->fixtures->getReference('partcategory.root'))
			->setStorageLocation($this->fixtures->getReference('storagelocation.first'));

		$partManufacturer = (new PartManufacturer)
			->setManufacturer($this->fixtures->getReference('manufacturer.first'));
		$part->addManufacturer($partManufacturer);

		$partDistributor = (new PartDistributor)
			->setDistributor($this->fixtures->getReference('distributor.first'));
		$part->addDistributor($partDistributor);

		$partAttachment = new PartAttachment;

		$fileService = $container->get(UploadedFileService::class);
		$fileService->replaceFromData($partAttachment, 'BLA', 'test.txt');

		$part->addAttachment($partAttachment);

		$container->get(EntityManagerInterface::class)->persist($part);
		$container->get(EntityManagerInterface::class)->flush($part);

		$part->removeDistributor($partDistributor);
		$part->removeManufacturer($partManufacturer);
		$part->removeAttachment($partAttachment);

		$container->get(EntityManagerInterface::class)->flush($part);

		$storage = $fileService->getStorage($partAttachment);

		self::assertNull($partDistributor->getId());
		self::assertNull($partDistributor->getId());
		self::assertNull($partAttachment->getId());
		self::assertFalse($storage->has($partAttachment->getFullFilename()));
	}
}
