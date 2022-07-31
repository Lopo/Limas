<?php

namespace Limas\Tests\DataFixtures;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Persistence\ObjectManager;
use Limas\Entity\Manufacturer;


class ManufacturerDataLoader
	extends AbstractFixture
{
	public function load(ObjectManager $manager)
	{
		$manufacturer = (new Manufacturer)
			->setName('TEST');

		$manager->persist($manufacturer);
		$manager->flush();

		$this->addReference('manufacturer.first', $manufacturer);
	}
}
