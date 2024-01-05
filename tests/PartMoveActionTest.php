<?php

namespace Limas\Tests;

use Limas\Entity\PartCategory;
use Limas\Tests\DataFixtures\PartCategoryDataLoader;


class PartMoveActionTest
	extends AbstractMoveCategoryTestBase
{
	public function getFixtureLoaderClass(): string
	{
		return PartCategoryDataLoader::class;
	}

	public function getReferencePrefix(): string
	{
		return 'partcategory';
	}

	protected function getResourceClass(): string
	{
		return PartCategory::class;
	}
}
