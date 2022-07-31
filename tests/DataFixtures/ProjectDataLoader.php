<?php

namespace Limas\Tests\DataFixtures;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Persistence\ObjectManager;
use Limas\Entity\Project;
use Limas\Entity\ProjectPart;


class ProjectDataLoader
	extends AbstractFixture
{
	public function load(ObjectManager $manager)
	{
		$projectPart1 = (new ProjectPart)
			->setPart($this->getReference('part.1'))
			->setQuantity(1)
			->setOverageType(ProjectPart::OVERAGE_TYPE_ABSOLUTE)
			->setOverage(0);

		$projectPart2 = (new ProjectPart)
			->setPart($this->getReference('part.2'))
			->setQuantity(1)
			->setOverageType(ProjectPart::OVERAGE_TYPE_ABSOLUTE)
			->setOverage(0);

		$project = (new Project)
			->setName('FOOBAR')
			->setDescription('none')
			->addPart($projectPart1)
			->addPart($projectPart2);

		$manager->persist($project);
		$manager->persist($projectPart1);
		$manager->persist($projectPart2);
		$manager->flush();

		$this->addReference('project', $project);
		$this->addReference('projectpart.1', $projectPart1);
		$this->addReference('projectpart.2', $projectPart2);
	}
}
