<?php

namespace Limas\Tests\DataFixtures;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Persistence\ObjectManager;
use Limas\Entity\TipOfTheDay;


class TipOfTheDayDataLoader
	extends AbstractFixture
{
	public function load(ObjectManager $manager)
	{
		$tipOfTheDay = (new TipOfTheDay)
			->setName('FOO');

		$manager->persist($tipOfTheDay);
		$manager->flush();

		$this->addReference('tipoftheday', $tipOfTheDay);
	}
}
