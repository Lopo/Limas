<?php

namespace Limas\Tests;

use Doctrine\Common\DataFixtures\ReferenceRepository;
use Liip\FunctionalTestBundle\Test\WebTestCase;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Limas\Entity\ProjectAttachment;
use Limas\Entity\ProjectPart;
use Limas\Service\UploadedFileService;
use Limas\Tests\DataFixtures\PartCategoryDataLoader;
use Limas\Tests\DataFixtures\PartDataLoader;
use Limas\Tests\DataFixtures\ProjectDataLoader;
use Limas\Tests\DataFixtures\StorageLocationCategoryDataLoader;
use Limas\Tests\DataFixtures\StorageLocationDataLoader;
use Limas\Tests\DataFixtures\UserDataLoader;
use Nette\Utils\Json;
use Symfony\Component\HttpFoundation\File\UploadedFile;


class ProjectTest
	extends WebTestCase
{
	protected ReferenceRepository $fixtures;


	protected function setUp(): void
	{
		parent::setUp();
		$this->fixtures = static::getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
			UserDataLoader::class,
			StorageLocationCategoryDataLoader::class,
			StorageLocationDataLoader::class,
			PartCategoryDataLoader::class,
			PartDataLoader::class,
			ProjectDataLoader::class
		])->getReferenceRepository();
	}

	public function testCreateProject(): void
	{
		$client = static::makeAuthenticatedClient();

		$file = __DIR__ . '/DataFixtures/files/uploadtest.png';

		$client->request(
			'POST',
			'/api/temp_uploaded_files/upload',
			[],
			[
				'userfile' => new UploadedFile($file, 'uploadtest.png', 'image/png', null, true)
			]
		);

		$uploadedFile = Json::decode($client->getResponse()->getContent());

		$part = $this->fixtures->getReference('part.1');
		$part2 = $this->fixtures->getReference('part.2');

		$serializedPart1 = $this->getContainer()->get('serializer')->normalize($part, 'jsonld');
		$serializedPart2 = $this->getContainer()->get('serializer')->normalize($part2, 'jsonld');

		$client->request(
			'POST',
			'/api/projects',
			[],
			[],
			['CONTENT_TYPE' => 'application/json'],
			Json::encode([
				'name' => 'foobar',
				'description' => 'testdescription',
				'attachments' => [
					$uploadedFile->image,
				],
				'parts' => [
					[
						'quantity' => 1,
						'part' => $this->getContainer()->get('serializer')->normalize($part, 'jsonld'),
						'remarks' => 'testremark',
						'overageType' => ProjectPart::OVERAGE_TYPE_ABSOLUTE,
						'overage' => 0,
					],
					[
						'quantity' => 2,
						'part' => $serializedPart2,
						'remarks' => 'testremark2',
						'overageType' => ProjectPart::OVERAGE_TYPE_ABSOLUTE,
						'overage' => 0,
					]
				]
			])
		);

		$response = Json::decode($client->getResponse()->getContent());

		$this->assertObjectHasAttribute('@type', $response);
		$this->assertEquals('Project', $response->{'@type'});

		$this->assertObjectHasAttribute('name', $response);
		$this->assertEquals('foobar', $response->name);

		$this->assertObjectHasAttribute('description', $response);
		$this->assertEquals('testdescription', $response->description);

		$this->assertObjectHasAttribute('parts', $response);
		$this->assertIsArray($response->parts);

		$this->assertCount(2, $response->parts);
		$this->assertArrayHasKey(0, $response->parts);
		$this->assertEquals('ProjectPart', $response->parts[0]->{'@type'});
		$this->assertEquals(1, $response->parts[0]->quantity);
		$this->assertEquals('testremark', $response->parts[0]->remarks);
		$this->assertEquals('Part', $response->parts[0]->part->{'@type'});

		$this->assertObjectHasAttribute('attachments', $response);
		$this->assertCount(1, $response->attachments);
		$this->assertArrayHasKey(0, $response->attachments);
		$this->assertEquals('ProjectAttachment', $response->attachments[0]->{'@type'});

		unset($response->parts[0]);
	}

	public function testProjectPartRemoval(): void
	{
		$client = static::makeAuthenticatedClient();

		$project = $this->fixtures->getReference('project')
			->removePart($this->fixtures->getReference('projectpart.1'));

		$client->request(
			'PUT',
			$this->getContainer()->get('api_platform.iri_converter')->getIriFromItem($project),
			[],
			[],
			['CONTENT_TYPE' => 'application/json'],
			Json::encode($this->getContainer()->get('serializer')->normalize($project, 'jsonld'))
		);

		$response = Json::decode($client->getResponse()->getContent());

		$this->assertIsArray($response->parts);
		$this->assertArrayNotHasKey(
			1,
			$response->parts,
			'When removing an entry from the ArrayCollection, the array must be resorted!'
		);
		$this->assertCount(1, $response->parts);
	}

	public function testProjectAttachmentRemoval(): void
	{
		$em=$this->getContainer()->get('doctrine.orm.default_entity_manager');
		$client = static::makeAuthenticatedClient();

		$project = $this->fixtures->getReference('project');
		$em->refresh($project);

		$projectAttachment = new ProjectAttachment;
		$this->getContainer()->get(UploadedFileService::class)
			->replaceFromData($projectAttachment, 'BLA', 'test.txt');

		$project->addAttachment($projectAttachment);
		$em->flush($project);

		$project->removeAttachment($projectAttachment);

		$client->request(
			'PUT',
			$this->getContainer()->get('api_platform.iri_converter')->getIriFromItem($project),
			[],
			[],
			['CONTENT_TYPE' => 'application/json'],
			Json::encode($this->getContainer()->get('serializer')->normalize($project, 'jsonld'))
		);

		$response = Json::decode($client->getResponse()->getContent());

		$this->assertIsArray($response->attachments);
		$this->assertArrayNotHasKey(
			1,
			$response->attachments,
			'When removing an entry from the ArrayCollection, the array must be resorted!'
		);

		$this->assertCount(0, $response->attachments);
	}

	/**
	 * Tests that the project part does not contain a reference to the project. This is because we serialize the
	 * project reference as IRI and not as object, which causes problems when reading in the project part in the
	 * frontend and serializing it back.
	 */
	public function testAbsentProjectReference(): void
	{
		$client = static::makeAuthenticatedClient();

		$project = $this->fixtures->getReference('project');

		$iriConverter = $this->getContainer()->get('api_platform.iri_converter');
		$iri = $iriConverter->getIriFromItem($project);

		$client->request('GET', $iri);

		$project = Json::decode($client->getResponse()->getContent());

		$this->assertObjectHasAttribute('parts', $project);
		$this->assertIsArray($project->parts);

//		foreach ($project->parts as $part) {
//			$this->assertObjectNotHasAttribute('project', $part);
//		}
	}
}
