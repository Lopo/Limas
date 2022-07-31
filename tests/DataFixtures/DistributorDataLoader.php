<?php

namespace Limas\Tests\DataFixtures;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Persistence\ObjectManager;
use Limas\Entity\Distributor;


class DistributorDataLoader
	extends AbstractFixture
{
	public function load(ObjectManager $manager)
	{
		$distributor = (new Distributor)
			->setName('TEST');

		$manager->persist($distributor);
		$manager->flush();

		$this->addReference('distributor.first', $distributor);
	}
}
