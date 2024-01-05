<?php

namespace Limas\Tests;

use Limas\Entity\FootprintCategory;
use Limas\Tests\DataFixtures\FootprintCategoryDataLoader;


class FootprintMoveActionTest
	extends AbstractMoveCategoryTestBase
{
	public function getFixtureLoaderClass(): string
	{
		return FootprintCategoryDataLoader::class;
	}

	public function getReferencePrefix(): string
	{
		return 'footprintcategory';
	}

	protected function getResourceClass(): string
	{
		return FootprintCategory::class;
	}
}
