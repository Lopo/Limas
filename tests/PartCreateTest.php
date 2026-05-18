<?php

namespace Limas\Tests;

use Doctrine\Common\DataFixtures\ReferenceRepository;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Limas\Entity\Part;
use Limas\Entity\PartCategory;
use Limas\Entity\StorageLocation;
use Limas\Tests\DataFixtures\PartCategoryDataLoader;
use Limas\Tests\DataFixtures\PartDataLoader;
use Limas\Tests\DataFixtures\StorageLocationCategoryDataLoader;
use Limas\Tests\DataFixtures\StorageLocationDataLoader;
use Limas\Tests\DataFixtures\UserDataLoader;
use Nette\Utils\Json;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;


class PartCreateTest
	extends WebTestCase
{
	protected ReferenceRepository $fixtures;


	private function getPartIdFromIri(string $iri): int
	{
		preg_match('/\/api\/parts\/(\d+)/', $iri, $matches);
		return (int)$matches[1];
	}

	private function assertPartAttachmentsInDb(int $partId, int $expectedCount): void
	{
		$part = self::getContainer()->get('doctrine.orm.entity_manager')->find(Part::class, $partId);
		self::assertNotNull($part, "Part with ID $partId not found in DB");
		self::assertCount($expectedCount, $part->getAttachments(), "Expected $expectedCount attachments in DB");
	}

	protected function setUp(): void
	{
		parent::setUp();
		$this->fixtures = self::getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
			UserDataLoader::class,
			StorageLocationCategoryDataLoader::class,
			StorageLocationDataLoader::class,
			PartCategoryDataLoader::class,
			PartDataLoader::class
		])->getReferenceRepository();
	}

	public function testCreatePartWithoutAttachments(): void
	{
		$client = $this->makeAuthenticatedClient();

		$category = $this->fixtures->getReference('partcategory.first', PartCategory::class);
		$storageLocation = $this->fixtures->getReference('storagelocation.first', StorageLocation::class);

		$pName = 'Test Part without Attachments';

		$client->request(
			'POST',
			'/api/parts',
			[],
			[],
			['CONTENT_TYPE' => 'application/json'],
			Json::encode([
				'name' => $pName,
				'description' => 'Simple part without any attachments',
				'category' => '/api/part_categories/' . $category->getId(),
				'storageLocation' => '/api/storage_locations/' . $storageLocation->getId(),
				'minStockLevel' => 0
			])
		);

		self::assertEquals(Response::HTTP_CREATED, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());
		$createdPart = Json::decode($client->getResponse()->getContent());

		self::assertEquals($pName, $createdPart->name);
		self::assertEquals(0, $createdPart->stockLevel);

		// Verify in DB
		$this->assertPartAttachmentsInDb($this->getPartIdFromIri($createdPart->{'@id'}), 0);

		// Cleanup
		$client->request('DELETE', $createdPart->{'@id'});
		self::assertEquals(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());
	}

	public function testCreatePartWithAttachmentAndStock(): void
	{
		$client = $this->makeAuthenticatedClient();

		$client->request(
			'POST',
			'/api/temp_uploaded_files/upload',
			['url' => 'https://httpbin.org/image/png']
		);
		self::assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
		$uploadResponse = Json::decode($client->getResponse()->getContent());
		self::assertTrue($uploadResponse->success);
		$tempFileId = $uploadResponse->response->{'@id'};

		$category = $this->fixtures->getReference('partcategory.first', PartCategory::class);
		$storageLocation = $this->fixtures->getReference('storagelocation.first', StorageLocation::class);

		$pName = 'Test Part with Attachment';

		$client->request(
			'POST',
			'/api/parts',
			[],
			[],
			['CONTENT_TYPE' => 'application/json'],
			Json::encode([
				'name' => $pName,
				'description' => 'Created via PHPUnit test',
				'category' => '/api/part_categories/' . $category->getId(),
				'storageLocation' => '/api/storage_locations/' . $storageLocation->getId(),
				'minStockLevel' => 5,
				'attachments' => [
					['@id' => $tempFileId]
				],
				'stockLevels' => [
					[
						'stockLevel' => 10,
						'price' => '0',
						'dateTime' => (new \DateTime)->format('c'),
						'correction' => false
					]
				]
			])
		);

		$resp = $client->getResponse();
		self::assertEquals(Response::HTTP_CREATED, $resp->getStatusCode(), $resp->getContent());
		$createdPart = Json::decode($resp->getContent());

		self::assertEquals($pName, $createdPart->name);
		self::assertEquals('Created via PHPUnit test', $createdPart->description);
		self::assertEquals(10, $createdPart->stockLevel);
		self::assertNotEmpty($createdPart->{'@id'});

		$client->request('GET', $createdPart->{'@id'});
		$resp = $client->getResponse();
		self::assertEquals(Response::HTTP_OK, $resp->getStatusCode());
		$fetchedPart = Json::decode($resp->getContent());

		self::assertEquals($pName, $fetchedPart->name);
		self::assertEquals(10, $fetchedPart->stockLevel);

		// Verify in DB
		$this->assertPartAttachmentsInDb($this->getPartIdFromIri($createdPart->{'@id'}), 1);

		$client->request('DELETE', $createdPart->{'@id'});
		self::assertEquals(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());
	}

	public function testCreatePartWithFileUploadAttachment(): void
	{
		$client = $this->makeAuthenticatedClient();

		$client->request(
			'POST',
			'/api/temp_uploaded_files/upload',
			[],
			['userfile' => new UploadedFile(__DIR__ . '/DataFixtures/files/uploadtest.png', 'uploadtest.png', 'image/png', null, true)]
		);
		$resp = $client->getResponse();
		self::assertEquals(Response::HTTP_OK, $resp->getStatusCode());
		$uploadResponse = Json::decode($resp->getContent());
		$tempFileId = $uploadResponse->response->{'@id'};

		$category = $this->fixtures->getReference('partcategory.first', PartCategory::class);
		$storageLocation = $this->fixtures->getReference('storagelocation.first', StorageLocation::class);

		$pName = 'Test Part with File Upload';

		$client->request(
			'POST',
			'/api/parts',
			[],
			[],
			['CONTENT_TYPE' => 'application/json'],
			Json::encode([
				'name' => $pName,
				'category' => '/api/part_categories/' . $category->getId(),
				'storageLocation' => '/api/storage_locations/' . $storageLocation->getId(),
				'attachments' => [
					['@id' => $tempFileId]
				]
			])
		);
		$resp = $client->getResponse();
		self::assertEquals(Response::HTTP_CREATED, $resp->getStatusCode(), $resp->getContent());
		$createdPart = Json::decode($resp->getContent());

		self::assertEquals($pName, $createdPart->name);

		// Verify in DB
		$this->assertPartAttachmentsInDb($this->getPartIdFromIri($createdPart->{'@id'}), 1);

		// Cleanup
		$client->request('DELETE', $createdPart->{'@id'});
		self::assertEquals(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());
	}

	public function testCreatePartWithTwoFileAttachments(): void
	{
		$client = $this->makeAuthenticatedClient();

		// Upload first file
		$file = __DIR__ . '/DataFixtures/files/uploadtest.png';
		$client->request('POST', '/api/temp_uploaded_files/upload', [], ['userfile' => new UploadedFile($file, 'file1.png', 'image/png', null, true)]);
		$tempFileId1 = Json::decode($client->getResponse()->getContent())->response->{'@id'};

		// Upload second file
		$client->request('POST', '/api/temp_uploaded_files/upload', [], ['userfile' => new UploadedFile($file, 'file2.png', 'image/png', null, true)]);
		$tempFileId2 = Json::decode($client->getResponse()->getContent())->response->{'@id'};

		// Get references
		$category = $this->fixtures->getReference('partcategory.first', PartCategory::class);
		$storageLocation = $this->fixtures->getReference('storagelocation.first', StorageLocation::class);

		$pName = 'Test Part with 2 File Attachments';

		// Create part with 2 file attachments
		$client->request(
			'POST',
			'/api/parts',
			[],
			[],
			['CONTENT_TYPE' => 'application/json'],
			Json::encode([
				'name' => $pName,
				'category' => '/api/part_categories/' . $category->getId(),
				'storageLocation' => '/api/storage_locations/' . $storageLocation->getId(),
				'attachments' => [
					['@id' => $tempFileId1],
					['@id' => $tempFileId2]
				]
			])
		);

		$resp = $client->getResponse();
		self::assertEquals(Response::HTTP_CREATED, $resp->getStatusCode(), $resp->getContent());
		$createdPart = Json::decode($resp->getContent());

		self::assertEquals($pName, $createdPart->name);

		// Verify in DB
		$this->assertPartAttachmentsInDb($this->getPartIdFromIri($createdPart->{'@id'}), 2);

		// Cleanup
		$client->request('DELETE', $createdPart->{'@id'});
	}

	public function testCreatePartWithTwoUrlAttachments(): void
	{
		$client = $this->makeAuthenticatedClient();

		// Upload first URL
		$client->request('POST', '/api/temp_uploaded_files/upload', ['url' => 'https://httpbin.org/image/png']);
		$tempFileId1 = Json::decode($client->getResponse()->getContent())->response->{'@id'};

		// Upload second URL
		$client->request('POST', '/api/temp_uploaded_files/upload', ['url' => 'https://httpbin.org/image/jpeg']);
		$tempFileId2 = Json::decode($client->getResponse()->getContent())->response->{'@id'};

		// Get references
		$category = $this->fixtures->getReference('partcategory.first', PartCategory::class);
		$storageLocation = $this->fixtures->getReference('storagelocation.first', StorageLocation::class);

		$pName = 'Test Part with 2 URL Attachments';

		// Create part with 2 URL attachments
		$client->request(
			'POST',
			'/api/parts',
			[],
			[],
			['CONTENT_TYPE' => 'application/json'],
			Json::encode([
				'name' => $pName,
				'category' => '/api/part_categories/' . $category->getId(),
				'storageLocation' => '/api/storage_locations/' . $storageLocation->getId(),
				'attachments' => [
					['@id' => $tempFileId1],
					['@id' => $tempFileId2]
				]
			])
		);

		$resp = $client->getResponse();
		self::assertEquals(Response::HTTP_CREATED, $resp->getStatusCode(), $resp->getContent());
		$createdPart = Json::decode($resp->getContent());

		self::assertEquals($pName, $createdPart->name);

		// Verify in DB
		$this->assertPartAttachmentsInDb($this->getPartIdFromIri($createdPart->{'@id'}), 2);

		// Cleanup
		$client->request('DELETE', $createdPart->{'@id'});
	}

	public function testCreatePartWithMixedAttachments(): void
	{
		$client = $this->makeAuthenticatedClient();

		// Upload file
		$file = __DIR__ . '/DataFixtures/files/uploadtest.png';
		$client->request('POST', '/api/temp_uploaded_files/upload', [], ['userfile' => new UploadedFile($file, 'file.png', 'image/png', null, true)]);
		$tempFileId1 = Json::decode($client->getResponse()->getContent())->response->{'@id'};

		// Upload URL
		$client->request('POST', '/api/temp_uploaded_files/upload', ['url' => 'https://httpbin.org/image/png']);
		$tempFileId2 = Json::decode($client->getResponse()->getContent())->response->{'@id'};

		// Get references
		$category = $this->fixtures->getReference('partcategory.first', PartCategory::class);
		$storageLocation = $this->fixtures->getReference('storagelocation.first', StorageLocation::class);

		$pName = 'Test Part with Mixed Attachments';

		// Create part with mixed attachments (1 file + 1 URL)
		$client->request(
			'POST',
			'/api/parts',
			[],
			[],
			['CONTENT_TYPE' => 'application/json'],
			Json::encode([
				'name' => $pName,
				'category' => '/api/part_categories/' . $category->getId(),
				'storageLocation' => '/api/storage_locations/' . $storageLocation->getId(),
				'attachments' => [
					['@id' => $tempFileId1],
					['@id' => $tempFileId2]
				]
			])
		);

		$resp = $client->getResponse();
		self::assertEquals(Response::HTTP_CREATED, $resp->getStatusCode(), $resp->getContent());
		$createdPart = Json::decode($resp->getContent());

		self::assertEquals($pName, $createdPart->name);

		// Verify in DB
		$this->assertPartAttachmentsInDb($this->getPartIdFromIri($createdPart->{'@id'}), 2);

		// Cleanup
		$client->request('DELETE', $createdPart->{'@id'});
	}
}
