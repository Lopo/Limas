<?php

namespace Limas\Tests;

use Limas\Tests\DataFixtures\PartCategoryDataLoader;


class PartMoveActionTest
	extends AbstractMoveCategoryTest
{
	public function getFixtureLoaderClass()
	{
		return PartCategoryDataLoader::class;
	}

	public function getReferencePrefix()
	{
		return 'partcategory';
	}
}
