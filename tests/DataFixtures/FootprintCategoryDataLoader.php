<?php

namespace Limas\Tests\DataFixtures;

use Limas\Entity\FootprintCategory;


class FootprintCategoryDataLoader
	extends AbstractCategoryDataLoader
{
	protected function getEntityClass(): string
	{
		return FootprintCategory::class;
	}

	protected function getReferencePrefix(): string
	{
		return 'footprintcategory';
	}
}
