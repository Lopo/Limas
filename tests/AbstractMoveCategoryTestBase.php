<?php

namespace Limas\Tests;

use ApiPlatform\Metadata\IriConverterInterface;
use Doctrine\Common\DataFixtures\ReferenceRepository;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Limas\Tests\DataFixtures\UserDataLoader;
use Nette\Utils\Json;


abstract class AbstractMoveCategoryTestBase
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

	public function testMoveCategory(): void
	{
		$client = $this->makeAuthenticatedClient();

		$secondCategory = $this->fixtures->getReference($this->getReferencePrefix() . '.second', $this->getResourceClass());
		$rootCategory = $this->fixtures->getReference($this->getReferencePrefix() . '.root', $this->getResourceClass());

		$iriConverter = self::getContainer()->get(IriConverterInterface::class);

		$client->request(
			'PUT',
			$iriConverter->getIriFromResource($secondCategory) . '/move',
			[],
			[],
			['CONTENT_TYPE' => 'application/json'],
			Json::encode(['parent' => $iriConverter->getIriFromResource($rootCategory)])
		);

		self::assertEquals($rootCategory->getId(), $secondCategory->getParent()->getId());
		self::assertEquals('Root Node ➤ Second Category', $secondCategory->getCategoryPath());
	}

	abstract public function getFixtureLoaderClass(): string;

	abstract public function getReferencePrefix(): string;

	abstract protected function getResourceClass(): string;
}
