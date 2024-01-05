<?php

namespace Limas\Tests;

use Limas\Entity\PartCategory;
use Limas\Tests\DataFixtures\PartCategoryDataLoader;


class PartCategoryCreateTest
	extends AbstractCategoryCreateTestBase
{
	public function getFixtureLoaderClass(): string
	{
		return PartCategoryDataLoader::class;
	}

	public function getReferencePrefix(): string
	{
		return 'partcategory';
	}

	public function getResourceClass(): string
	{
		return PartCategory::class;
	}

	public function getUriBase(): string
	{
		return '/api/part_categories';
	}
}
