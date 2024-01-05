<?php

namespace Limas\Tests;

use Doctrine\Common\DataFixtures\ReferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Limas\Entity\Part;
use Limas\Entity\Project;
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
use Symfony\Component\Serializer\SerializerInterface;


class ProjectTest
	extends WebTestCase
{
	protected ReferenceRepository $fixtures;


	protected function setUp(): void
	{
		parent::setUp();
		$this->fixtures = self::getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
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
		$client = $this->makeAuthenticatedClient();

		$file = __DIR__ . '/DataFixtures/files/uploadtest.png';

		$client->request(
			'POST',
			'/api/temp_uploaded_files/upload',
			[],
			['userfile' => new UploadedFile($file, 'uploadtest.png', 'image/png', null, true)]
		);

		$uploadedFile = Json::decode($client->getResponse()->getContent());

		$part = $this->fixtures->getReference('part.1', Part::class);
		$part2 = $this->fixtures->getReference('part.2', Part::class);

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
						'part' => '/api/parts/' . $part->getId(),
						'remarks' => 'testremark',
						'overageType' => ProjectPart::OVERAGE_TYPE_ABSOLUTE,
						'overage' => 0,
					],
					[
						'quantity' => 2,
						'part' => '/api/parts/' . $part2->getId(),
						'remarks' => 'testremark2',
						'overageType' => ProjectPart::OVERAGE_TYPE_ABSOLUTE,
						'overage' => 0,
					]
				]
			])
		);

		$response = Json::decode($client->getResponse()->getContent());

		self::assertObjectHasProperty('@type', $response);
		self::assertEquals('Project', $response->{'@type'});

		self::assertObjectHasProperty('name', $response);
		self::assertEquals('foobar', $response->name);

		self::assertObjectHasProperty('description', $response);
		self::assertEquals('testdescription', $response->description);

		self::assertObjectHasProperty('parts', $response);
		self::assertIsArray($response->parts);

		self::assertCount(2, $response->parts);
		self::assertArrayHasKey(0, $response->parts);
		self::assertEquals('ProjectPart', $response->parts[0]->{'@type'});
		self::assertEquals(1, $response->parts[0]->quantity);
		self::assertEquals('testremark', $response->parts[0]->remarks);
		self::assertEquals('Part', $response->parts[0]->part->{'@type'});

		self::assertObjectHasProperty('attachments', $response);
		self::assertCount(1, $response->attachments);
		self::assertArrayHasKey(0, $response->attachments);
		self::assertEquals('ProjectAttachment', $response->attachments[0]->{'@type'});

		unset($response->parts[0]);
	}

	public function testProjectPartRemoval(): void
	{
		$client = $this->makeAuthenticatedClient();

		$project = $this->fixtures->getReference('project', Project::class)
			->removePart($this->fixtures->getReference('projectpart.1', ProjectPart::class));

		$client->request(
			'PUT',
			'/api/projects/' . $project->getId(),
			[],
			[],
			['CONTENT_TYPE' => 'application/json'],
			Json::encode(self::getContainer()->get(SerializerInterface::class)->normalize($project, 'jsonld'))
		);

		$response = Json::decode($client->getResponse()->getContent());

		self::assertIsArray($response->parts);
		self::assertArrayNotHasKey(
			1,
			$response->parts,
			'When removing an entry from the ArrayCollection, the array must be resorted!'
		);
		self::assertCount(1, $response->parts);
	}

	public function testProjectAttachmentRemoval(): void
	{
		$em = self::getContainer()->get(EntityManagerInterface::class);

		$client = $this->makeAuthenticatedClient();

		$project = $this->fixtures->getReference('project', Project::class);
		$em->refresh($project);

		$projectAttachment = new ProjectAttachment;
		self::getContainer()->get(UploadedFileService::class)
			->replaceFromData($projectAttachment, 'BLA', 'test.txt');

		$project->addAttachment($projectAttachment);
		$em->flush();

		$project->removeAttachment($projectAttachment);

		$client->request(
			'PUT',
			'/api/projects/' . $project->getId(),
			[],
			[],
			['CONTENT_TYPE' => 'application/json'],
			Json::encode(self::getContainer()->get(SerializerInterface::class)->normalize($project, 'jsonld'))
		);

		$response = Json::decode($client->getResponse()->getContent());

		self::assertIsArray($response->attachments);
		self::assertArrayNotHasKey(
			1,
			$response->attachments,
			'When removing an entry from the ArrayCollection, the array must be resorted!'
		);

		self::assertCount(0, $response->attachments);
	}

	/**
	 * Tests that the project part does not contain a reference to the project. This is because we serialize the
	 * project reference as IRI and not as object, which causes problems when reading in the project part in the
	 * frontend and serializing it back.
	 */
	public function testAbsentProjectReference(): void
	{
		$client = $this->makeAuthenticatedClient();

		$client->request('GET', '/api/projects/' . $this->fixtures->getReference('project', Project::class)->getId());

		$project = Json::decode($client->getResponse()->getContent());

		self::assertObjectHasProperty('parts', $project);
		self::assertIsArray($project->parts);

//		foreach ($project->parts as $part) {
//			self::assertObjectNotHasAttribute('project', $part);
//		}
	}
}
