<?php

namespace Limas\Tests;

use Doctrine\Common\DataFixtures\ReferenceRepository;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Limas\Tests\DataFixtures\UserDataLoader;
use PHPUnit\Util\Json;


abstract class AbstractCategoryGetRootNodeTestBase
	extends WebTestCase
{
	protected ReferenceRepository $fixtures;


	protected function setUp(): void
	{
		parent::setUp();
		$this->fixtures = self::getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
			UserDataLoader::class,
			$this->getFixtureLoaderClass()
		])->getReferenceRepository();
	}

	public function testGetRootNode(): void
	{
		[$errexp, $recexp] = Json::canonicalize($this->getExpected());
		self::assertFalse($errexp);

		$client = $this->makeAuthenticatedClient();
		$client->request('GET', $this->getUriBase() . '/getExtJSRootNode');

		[$errres, $recres] = Json::canonicalize($client->getResponse()->getContent());
		self::assertFalse($errres);
		self::assertSame($recexp, $recres);
	}

	abstract protected function getFixtureLoaderClass(): string;

	abstract protected function getResourceClass(): string;

	abstract protected function getExpected(): string;

	abstract protected function getUriBase(): string;
}
