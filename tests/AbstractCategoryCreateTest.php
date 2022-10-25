<?php

namespace Limas\Tests;

use ApiPlatform\Core\Api\IriConverterInterface;
use Doctrine\Common\DataFixtures\ReferenceRepository;
use Liip\FunctionalTestBundle\Test\WebTestCase;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Limas\Tests\DataFixtures\UserDataLoader;
use Nette\Utils\Json;


abstract class AbstractCategoryCreateTest
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

	public function testCreateCategory(): void
	{
		$client = static::makeAuthenticatedClient();

		$rootCategory = $this->fixtures->getReference($this->getReferencePrefix() . '.root');

		$iriConverter = $this->getContainer()->get(IriConverterInterface::class);

		$client->request(
			'POST',
			$iriConverter->getIriFromResourceClass($this->getResourceClass()),
			[],
			[],
			['CONTENT_TYPE' => 'application/json'],
			Json::encode([
				'parent' => $iriConverter->getIriFromItem($rootCategory),
				'name' => 'test',
			])
		);

		$responseObject = Json::decode($client->getResponse()->getContent());

		self::assertIsObject($responseObject);

		self::assertObjectHasAttribute('@id', $responseObject);
		self::assertObjectHasAttribute('name', $responseObject);

		$item = $iriConverter->getItemFromIri($responseObject->{'@id'});

		self::assertNotNull($item->getParent());
		self::assertEquals($item->getParent()->getId(), $rootCategory->getId());
	}

	public function testCreateRootCategory(): void
	{
		$client = static::makeAuthenticatedClient();

		$client->request(
			'POST',
			$this->getContainer()->get(IriConverterInterface::class)->getIriFromResourceClass($this->getResourceClass()),
			[],
			[],
			['CONTENT_TYPE' => 'application/json'],
			Json::encode([
				'name' => 'test'
			])
		);

		$responseObject = Json::decode($client->getResponse()->getContent());

		self::assertObjectHasAttribute('@type', $responseObject);
		self::assertObjectHasAttribute('hydra:description', $responseObject);

		self::assertEquals('There may be only one root node', $responseObject->{'hydra:description'});
	}

	abstract public function getFixtureLoaderClass(): string;

	abstract public function getReferencePrefix(): string;

	abstract public function getResourceClass(): string;
}
