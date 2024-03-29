<?php

namespace Limas\Tests;

use Limas\Entity\FootprintCategory;
use Limas\Tests\DataFixtures\FootprintCategoryDataLoader;


class FootprintCategoryCreateTest
	extends AbstractCategoryCreateTestBase
{
	public function getFixtureLoaderClass(): string
	{
		return FootprintCategoryDataLoader::class;
	}

	public function getReferencePrefix(): string
	{
		return 'footprintcategory';
	}

	public function getResourceClass(): string
	{
		return FootprintCategory::class;
	}

	public function getUriBase(): string
	{
		return '/api/footprint_categories';
	}
}
