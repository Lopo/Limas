<?php

namespace Limas\Tests;

use Limas\Tests\DataFixtures\FootprintCategoryDataLoader;


class FootprintMoveActionTest
	extends AbstractMoveCategoryTest
{
	public function getFixtureLoaderClass()
	{
		return FootprintCategoryDataLoader::class;
	}

	public function getReferencePrefix()
	{
		return 'footprintcategory';
	}
}
