<?php

namespace Limas\Tests\DataFixtures;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Persistence\ObjectManager;
use Limas\Entity\User;
use Limas\Entity\UserProvider;
use Limas\Service\UserService;


class UserDataLoader
	extends AbstractFixture
{
	public function load(ObjectManager $manager)
	{
		$builtin = new UserProvider(UserService::BUILTIN_PROVIDER, true);
		$manager->persist($builtin);

		$admin = (new User('admin'))
			->setPassword('admin')
			->setEmail('foo@bar.com')
			->setProvider($builtin);

		$manager->persist($admin);
		$manager->flush();

		$this->addReference('user.admin', $admin);
	}
}
