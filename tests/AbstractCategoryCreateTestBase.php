<?php

namespace Limas\Tests;

use ApiPlatform\Api\IriConverterInterface;
use Doctrine\Common\DataFixtures\ReferenceRepository;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Limas\Tests\DataFixtures\UserDataLoader;
use Nette\Utils\Json;


abstract class AbstractCategoryCreateTestBase
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

	public function testCreateCategory(): void
	{
		$client = $this->makeAuthenticatedClient();

		$rootCategory = $this->fixtures->getReference($this->getReferencePrefix() . '.root', $this->getResourceClass());

		$iriConverter = self::getContainer()->get(IriConverterInterface::class);

		$client->request(
			'POST',
			$this->getUriBase(),
			[],
			[],
			['CONTENT_TYPE' => 'application/json'],
			Json::encode([
				'parent' => $iriConverter->getIriFromResource($rootCategory),
				'name' => 'test',
			])
		);

		$responseObject = Json::decode($client->getResponse()->getContent());

		self::assertIsObject($responseObject);

		self::assertObjectHasProperty('@id', $responseObject);
		self::assertObjectHasProperty('name', $responseObject);

		$item = $iriConverter->getResourceFromIri($responseObject->{'@id'});

		self::assertNotNull($item->getParent());
		self::assertEquals($item->getParent()->getId(), $rootCategory->getId());
	}

	public function testCreateRootCategory(): void
	{
		$client = $this->makeAuthenticatedClient();

		$client->request(
			'POST',
			$this->getUriBase(),
			[],
			[],
			['CONTENT_TYPE' => 'application/json'],
			Json::encode([
				'name' => 'test'
			])
		);

		$responseObject = Json::decode($client->getResponse()->getContent());

		self::assertObjectHasProperty('@type', $responseObject);
		self::assertObjectHasProperty('hydra:description', $responseObject);

		self::assertEquals('There may be only one root node', $responseObject->{'hydra:description'});
	}

	abstract public function getFixtureLoaderClass(): string;

	abstract public function getReferencePrefix(): string;

	abstract public function getResourceClass(): string;

	abstract public function getUriBase(): string;
}
