<?php

namespace Limas\Command;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Entity\FootprintCategory;
use Limas\Entity\PartCategory;
use Limas\Entity\StorageLocationCategory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


#[AsCommand(
	name: 'limas:update-category-paths',
	description: 'Updates the category paths for all category trees'
)]
class UpdateCategoryPathsCommand
	extends Command
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly array                  $limas
	)
	{
		parent::__construct();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$entities = [
			FootprintCategory::class,
			PartCategory::class,
			StorageLocationCategory::class,
		];
		foreach ($entities as $entity) {
			$this->regenerateCategoryPaths($entity);
		}
		return Command::SUCCESS;
	}

	private function regenerateCategoryPaths(string $entity): void
	{
		$rootNodes = $this->entityManager->getRepository($entity)->getRootNodes();

		$pathSeparator = $this->limas['category']['path_separator'];

		foreach ($rootNodes as $rootNode) {
			$rootNode->setCategoryPath(uniqid('', true));
		}
		$this->entityManager->flush();

		foreach ($rootNodes as $rootNode) {
			$rootNode->setCategoryPath($rootNode->generateCategoryPath($pathSeparator));
		}
		$this->entityManager->flush();
	}
}
