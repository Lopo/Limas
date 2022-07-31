<?php

namespace Limas\Tests\DataFixtures;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Persistence\ObjectManager;
use Limas\Entity\User;


class UserDataLoader
	extends AbstractFixture
{
	public function load(ObjectManager $manager)
	{
		$admin = (new User('admin'))
			->setPassword('admin')
			->setEmail('foo@bar.com');

		$manager->persist($admin);
		$manager->flush();

		$this->addReference('user.admin', $admin);
	}
}
