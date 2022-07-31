<?php

namespace Limas\Tests;

use Limas\Tests\DataFixtures\StorageLocationCategoryDataLoader;


class StorageLocationMoveActionTest
	extends AbstractMoveCategoryTest
{
	public function getFixtureLoaderClass()
	{
		return StorageLocationCategoryDataLoader::class;
	}

	public function getReferencePrefix()
	{
		return 'storagelocationcategory';
	}
}
