<?php

namespace Limas\Tests\DataFixtures;

use Limas\Entity\PartCategory;


class PartCategoryDataLoader
	extends AbstractCategoryDataLoader
{
	protected function getEntityClass(): string
	{
		return PartCategory::class;
	}

	protected function getReferencePrefix(): string
	{
		return 'partcategory';
	}
}
