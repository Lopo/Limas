<?php

namespace Limas\Tests;

use Limas\Entity\PartCategory;
use Limas\Tests\DataFixtures\PartCategoryDataLoader;


class PartCategoryGetRootNodeTest
	extends AbstractCategoryGetRootNodeTestBase
{
	public function getFixtureLoaderClass(): string
	{
		return PartCategoryDataLoader::class;
	}

	public function getResourceClass(): string
	{
		return PartCategory::class;
	}

	protected function getExpected(): string
	{
		return '{"@context":"\/api\/contexts\/PartCategory","@id":"\/api\/part_categories\/1","@type":"PartCategory","parent":null,"children":[{"@id":"\/api\/part_categories\/2","@type":"PartCategory","parent":"\/api\/part_categories\/1","children":[{"@id":"\/api\/part_categories\/3","@type":"PartCategory","parent":"\/api\/part_categories\/2","children":[],"categoryPath":"Root Node ➤ First Category ➤ Second Category","name":"Second Category","description":null,"expanded":true,"id":3}],"categoryPath":"Root Node ➤ First Category","name":"First Category","description":null,"expanded":true,"id":2}],"categoryPath":"Root Node","name":"Root Node","description":null,"expanded":true,"id":1}';
	}

	protected function getUriBase(): string
	{
		return '/api/part_categories';
	}
}
