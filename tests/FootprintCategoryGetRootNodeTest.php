<?php

namespace Limas\Tests;

use Limas\Entity\FootprintCategory;
use Limas\Tests\DataFixtures\FootprintCategoryDataLoader;


class FootprintCategoryGetRootNodeTest
	extends AbstractCategoryGetRootNodeTestBase
{
	public function getFixtureLoaderClass(): string
	{
		return FootprintCategoryDataLoader::class;
	}

	public function getResourceClass(): string
	{
		return FootprintCategory::class;
	}

	protected function getExpected(): string
	{
		return '{"@context":"\/api\/contexts\/FootprintCategory","@id":"\/api\/footprint_categories\/1","@type":"FootprintCategory","parent":null,"children":[{"@id":"\/api\/footprint_categories\/2","@type":"FootprintCategory","parent":"\/api\/footprint_categories\/1","children":[{"@id":"\/api\/footprint_categories\/3","@type":"FootprintCategory","parent":"\/api\/footprint_categories\/2","children":[],"categoryPath":"Root Node ➤ First Category ➤ Second Category","name":"Second Category","description":null,"expanded":true,"id":3}],"categoryPath":"Root Node ➤ First Category","name":"First Category","description":null,"expanded":true,"id":2}],"categoryPath":"Root Node","name":"Root Node","description":null,"expanded":true,"id":1}';
	}

	protected function getUriBase(): string
	{
		return '/api/footprint_categories';
	}
}
