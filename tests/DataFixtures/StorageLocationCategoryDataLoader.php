<?php

namespace Limas\Tests\DataFixtures;

use Limas\Entity\StorageLocationCategory;


class StorageLocationCategoryDataLoader
	extends AbstractCategoryDataLoader
{
	protected function getEntityClass(): string
	{
		return StorageLocationCategory::class;
	}

	protected function getReferencePrefix(): string
	{
		return 'storagelocationcategory';
	}
}
