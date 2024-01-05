<?php

namespace Limas\Tests;

use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Limas\Tests\DataFixtures\UserDataLoader;
use Nette\Utils\Json;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;


class TemporaryFileControllerTest
	extends WebTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		self::getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
			UserDataLoader::class
		])->getReferenceRepository();
	}

	public function testUploadAction(): void
	{
		$client = $this->makeAuthenticatedClient();

		$file = __DIR__ . '/DataFixtures/files/uploadtest.png';
		$originalFilename = 'uploadtest.png';
		$mimeType = 'image/png';

		$image = new UploadedFile(
			$file,
			$originalFilename,
			$mimeType,
			null,
			true
		);

		$client->request(
			'POST',
			'/api/temp_uploaded_files/upload',
			[],
			['userfile' => $image]
		);

		self::assertEquals(200, $client->getResponse()->getStatusCode());
		$response = Json::decode($client->getResponse()->getContent());

		self::assertObjectHasProperty('success', $response);
		self::assertObjectHasProperty('image', $response);
		self::assertObjectHasProperty('response', $response);

		self::assertEquals(true, $response->success);

		$propertiesToCheck = [
			'@context',
			'@id',
			'@type',
			'originalFilename',
			'size',
			'type',
			'filename',
			'mimetype',
//			'description'
		];

		foreach ($propertiesToCheck as $property) {
			self::assertObjectHasProperty($property, $response->image);
			self::assertObjectHasProperty($property, $response->response);
		}

		self::assertEquals(filesize($file), $response->image->size);
		self::assertEquals(filesize($file), $response->response->size);

		self::assertEquals($originalFilename, $response->image->originalFilename);
		self::assertEquals($originalFilename, $response->response->originalFilename);

		self::assertEquals($mimeType, $response->image->mimetype);
		self::assertEquals($mimeType, $response->response->mimetype);

		self::assertEquals('tempfile', $response->image->type);
		self::assertEquals('tempfile', $response->response->type);

		$property = '@type';

		self::assertEquals('TempUploadedFile', $response->image->$property);
		self::assertEquals('TempUploadedFile', $response->response->$property);
	}

	public function testURLUploadAction(): void
	{
		$client = $this->makeAuthenticatedClient();

		$client->request(
			'POST',
			'/api/temp_uploaded_files/upload',
			['url' => 'https://partkeepr.org/images/partkeepr-banner.png']
		);

		$response = Json::decode($client->getResponse()->getContent());

		self::assertObjectHasProperty('success', $response);
		self::assertObjectHasProperty('image', $response);
		self::assertObjectHasProperty('response', $response);
	}

	public function testUploadException(): void
	{
		$client = $this->makeAuthenticatedClient();

		$client->request('POST', '/api/temp_uploaded_files/upload');

		$response = Json::decode($client->getResponse()->getContent());

		$attribute = '@type';

		self::assertObjectHasProperty($attribute, $response);
		self::assertEquals('hydra:Error', $response->$attribute);
	}

	public function testWebcamUploadAction(): void
	{
		$client = $this->makeAuthenticatedClient();

		$file = __DIR__ . '/DataFixtures/files/uploadtest.png';
		$fileString = 'data:image/png;base64,' . base64_encode(file_get_contents($file));

		$client->request(
			'POST',
			'/api/temp_uploaded_files/webcamUpload',
			[],
			[],
			[],
			$fileString
		);

		$response = Json::decode($client->getResponse()->getContent());

		$propertiesToCheck = [
			'@context',
			'@id',
			'@type',
			'originalFilename',
			'size',
			'type',
			'filename',
			'mimetype',
//			'description'
		];

		foreach ($propertiesToCheck as $property) {
			self::assertObjectHasProperty($property, $response);
		}

		self::assertEquals(filesize($file), $response->size);
		self::assertEquals('image/png', $response->mimetype);
		self::assertEquals('webcam.png', $response->originalFilename);
		self::assertEquals('tempfile', $response->type);

		$property = '@type';
		self::assertEquals('TempUploadedFile', $response->$property);
	}

	public function testGetFile(): void
	{
		$client = $this->makeAuthenticatedClient();

		$file = __DIR__ . '/DataFixtures/files/uploadtest.png';

		$image = new UploadedFile(
			$file,
			'uploadtest.png',
			'image/png',
			null,
			true
		);

		$client->request(
			'POST',
			'/api/temp_uploaded_files/upload',
			[],
			['userfile' => $image]
		);
		$response = Json::decode($client->getResponse()->getContent());
		$id = $response->response->{'@id'};

		$client->request(
			'GET',
			"$id/getFile"
		);
		$response = $client->getResponse();
		self::assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
		self::assertStringEqualsFile($file, $response->getContent());
	}

	public function testDeleteFile(): void
	{
		$client = $this->makeAuthenticatedClient();

		$image = new UploadedFile(
			__DIR__ . '/DataFixtures/files/uploadtest.png',
			'uploadtest.png',
			'image/png',
			null,
			true
		);

		$client->request(
			'POST',
			'/api/temp_uploaded_files/upload',
			[],
			['userfile' => $image]
		);
		$response = Json::decode($client->getResponse()->getContent());

		$client->request(
			'DELETE',
			$response->response->{'@id'}
		);
		self::assertEquals(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());
	}
}
