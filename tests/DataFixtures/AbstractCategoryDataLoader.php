<?php

namespace Limas\Tests\DataFixtures;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Persistence\ObjectManager;


abstract class AbstractCategoryDataLoader
	extends AbstractFixture
{
	public function load(ObjectManager $manager)
	{
		$entityClass = $this->getEntityClass();

		$rootNode = (new $entityClass)
			->setName('Root Node');
		$firstCategory = (new $entityClass)
			->setParent($rootNode)
			->setName('First Category');
		$secondCategory = (new $entityClass)
			->setParent($firstCategory)
			->setName('Second Category');

		$manager->persist($rootNode);
		$manager->persist($firstCategory);
		$manager->persist($secondCategory);
		$manager->flush();

		$this->addReference($this->getReferencePrefix() . '.root', $rootNode);
		$this->addReference($this->getReferencePrefix() . '.first', $firstCategory);
		$this->addReference($this->getReferencePrefix() . '.second', $secondCategory);
	}

	abstract protected function getEntityClass(): string;
	abstract protected function getReferencePrefix(): string;
}
