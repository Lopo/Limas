<?php

namespace Limas\Tests;

use Limas\Entity\StorageLocationCategory;
use Limas\Tests\DataFixtures\StorageLocationCategoryDataLoader;


class StorageLocationCategoryGetRootNodeTest
	extends AbstractCategoryGetRootNodeTestBase
{
	public function getFixtureLoaderClass(): string
	{
		return StorageLocationCategoryDataLoader::class;
	}

	public function getResourceClass(): string
	{
		return StorageLocationCategory::class;
	}

	protected function getExpected(): string
	{
		return '{"@context":"\/api\/contexts\/StorageLocationCategory","@id":"\/api\/storage_location_categories\/1","@type":"StorageLocationCategory","parent":null,"children":[{"@id":"\/api\/storage_location_categories\/2","@type":"StorageLocationCategory","parent":"\/api\/storage_location_categories\/1","children":[{"@id":"\/api\/storage_location_categories\/3","@type":"StorageLocationCategory","parent":"\/api\/storage_location_categories\/2","children":[],"categoryPath":"Root Node ➤ First Category ➤ Second Category","name":"Second Category","description":null,"expanded":true,"id":3}],"categoryPath":"Root Node ➤ First Category","name":"First Category","description":null,"expanded":true,"id":2}],"categoryPath":"Root Node","name":"Root Node","description":null,"expanded":true,"id":1}';
	}

	protected function getUriBase(): string
	{
		return '/api/storage_location_categories';
	}
}
