<?php

namespace Limas\Tests;

use Limas\Entity\StorageLocationCategory;
use Limas\Tests\DataFixtures\StorageLocationCategoryDataLoader;


class StorageLocationCategoryCreateTest
	extends AbstractCategoryCreateTest
{
	public function getFixtureLoaderClass(): string
	{
		return StorageLocationCategoryDataLoader::class;
	}

	public function getReferencePrefix(): string
	{
		return 'storagelocationcategory';
	}

	public function getResourceClass(): string
	{
		return StorageLocationCategory::class;
	}
}
