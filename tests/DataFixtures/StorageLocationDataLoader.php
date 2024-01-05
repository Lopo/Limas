<?php

namespace Limas\Tests\DataFixtures;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Persistence\ObjectManager;
use Limas\Entity\StorageLocation;
use Limas\Entity\StorageLocationCategory;


class StorageLocationDataLoader
	extends AbstractFixture
{
	public function load(ObjectManager $manager): void
	{
		$storageLocation = (new StorageLocation)
			->setName('test')
			->setCategory($this->getReference('storagelocationcategory.first', StorageLocationCategory::class));
		$storageLocation2 = (new StorageLocation)
			->setName('test2')
			->setCategory($this->getReference('storagelocationcategory.second', StorageLocationCategory::class));

		$manager->persist($storageLocation);
		$manager->persist($storageLocation2);
		$manager->flush();

		$this->addReference('storagelocation.first', $storageLocation);
		$this->addReference('storagelocation.second', $storageLocation2);
	}
}
