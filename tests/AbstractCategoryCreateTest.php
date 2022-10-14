<?php

namespace Limas\Tests;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Api\UrlGeneratorInterface;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
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

		/** @var IriConverterInterface $iriConverter */
		$iriConverter = $this->getContainer()->get('api_platform.iri_converter');

		$client->request(
			'POST',
			$iriConverter->getIriFromResource($this->getResourceClass(), UrlGeneratorInterface::ABS_PATH, (new GetCollection)->withClass($this->getResourceClass())),
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

		self::assertObjectHasAttribute('@id', $responseObject);
		self::assertObjectHasAttribute('name', $responseObject);

		$item = $iriConverter->getResourceFromIri($responseObject->{'@id'});

		self::assertNotNull($item->getParent());
		self::assertEquals($item->getParent()->getId(), $rootCategory->getId());
	}

	public function testCreateRootCategory(): void
	{
		$client = static::makeAuthenticatedClient();

		$client->request(
			'POST',
			$this->getContainer()->get('api_platform.iri_converter')->getIriFromResource($this->getResourceClass(), UrlGeneratorInterface::ABS_PATH, (new GetCollection)->withClass($this->getResourceClass())),
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
