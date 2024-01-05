<?php

namespace Limas\Tests;

use Limas\Entity\StorageLocationCategory;
use Limas\Tests\DataFixtures\StorageLocationCategoryDataLoader;


class StorageLocationCategoryCreateTest
	extends AbstractCategoryCreateTestBase
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

	public function getUriBase(): string
	{
		return '/api/storage_location_categories';
	}
}
