<?php

namespace Limas\Tests;

use Limas\Entity\StorageLocationCategory;
use Limas\Tests\DataFixtures\StorageLocationCategoryDataLoader;


class StorageLocationMoveActionTest
	extends AbstractMoveCategoryTestBase
{
	public function getFixtureLoaderClass(): string
	{
		return StorageLocationCategoryDataLoader::class;
	}

	public function getReferencePrefix(): string
	{
		return 'storagelocationcategory';
	}

	protected function getResourceClass(): string
	{
		return StorageLocationCategory::class;
	}
}
