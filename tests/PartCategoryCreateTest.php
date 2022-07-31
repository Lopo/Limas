<?php

namespace Limas\Tests;

use Limas\Entity\PartCategory;
use Limas\Tests\DataFixtures\PartCategoryDataLoader;


class PartCategoryCreateTest
	extends AbstractCategoryCreateTest
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
}
