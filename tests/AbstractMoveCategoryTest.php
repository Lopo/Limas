<?php

namespace Limas\Tests;

use ApiPlatform\Api\UrlGeneratorInterface;
use ApiPlatform\Metadata\Get;
use Doctrine\Common\DataFixtures\ReferenceRepository;
use Liip\FunctionalTestBundle\Test\WebTestCase;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Limas\Tests\DataFixtures\UserDataLoader;


abstract class AbstractMoveCategoryTest
	extends WebTestCase
{
	protected ReferenceRepository $fixtures;


	protected function setUp(): void
	{
		parent::setUp();
		$this->fixtures = $this->getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
			UserDataLoader::class,
			$this->getFixtureLoaderClass()
		])->getReferenceRepository();
	}

	public function testMoveCategory(): void
	{
		$client = static::makeAuthenticatedClient();

		$secondCategory = $this->fixtures->getReference($this->getReferencePrefix() . '.second');
		$rootCategory = $this->fixtures->getReference($this->getReferencePrefix() . '.root');

		$iriConverter = $this->getContainer()->get('api_platform.iri_converter');
		$iri = $iriConverter->getIriFromResource($secondCategory) . '/move';
		$targetIri = $iriConverter->getIriFromResource($rootCategory, UrlGeneratorInterface::ABS_PATH, (new Get)->withClass($rootCategory::class));

		$client->request(
			'PUT',
			$iri,
			['parent' => $targetIri],
			[],
			['CONTENT_TYPE' => 'application/x-www-form-urlencoded']
		);

		self::assertEquals($rootCategory->getId(), $secondCategory->getParent()->getId());
		self::assertEquals('Root Node ➤ Second Category', $secondCategory->getCategoryPath());
	}

	abstract public function getFixtureLoaderClass();

	abstract public function getReferencePrefix();
}
