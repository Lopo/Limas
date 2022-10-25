<?php

namespace Limas\Tests;

use Liip\FunctionalTestBundle\Test\WebTestCase;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Limas\Tests\DataFixtures\UserDataLoader;
use Nette\Utils\Json;
use Symfony\Component\HttpFoundation\File\UploadedFile;


class TemporaryFileControllerTest
	extends WebTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
			UserDataLoader::class
		])->getReferenceRepository();
	}

	public function testUploadAction(): void
	{
		$client = static::makeAuthenticatedClient();

		$file = __DIR__ . '/DataFixtures/files/uploadtest.png';
		$originalFilename = 'uploadtest.png';
		$mimeType = 'image/png';
		$extension = 'png';

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

		self::assertObjectHasAttribute('success', $response);
		self::assertObjectHasAttribute('image', $response);
		self::assertObjectHasAttribute('response', $response);

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
			'extension',
//			'description',
//			'legacyExtension'
		];

		foreach ($propertiesToCheck as $property) {
			self::assertObjectHasAttribute($property, $response->image);
			self::assertObjectHasAttribute($property, $response->response);
		}

		self::assertEquals(filesize($file), $response->image->size);
		self::assertEquals(filesize($file), $response->response->size);

		self::assertEquals($originalFilename, $response->image->originalFilename);
		self::assertEquals($originalFilename, $response->response->originalFilename);

		self::assertEquals($mimeType, $response->image->mimetype);
		self::assertEquals($mimeType, $response->response->mimetype);

		self::assertEquals($extension, $response->image->extension);
		self::assertEquals($extension, $response->response->extension);

		self::assertEquals('tempfile', $response->image->type);
		self::assertEquals('tempfile', $response->response->type);

		$property = '@type';

		self::assertEquals('TempUploadedFile', $response->image->$property);
		self::assertEquals('TempUploadedFile', $response->response->$property);
	}

	public function testURLUploadAction(): void
	{
		$client = static::makeAuthenticatedClient();

		$client->request(
			'POST',
			'/api/temp_uploaded_files/upload',
			['url' => 'https://partkeepr.org/images/partkeepr-banner.png']
		);

		$response = Json::decode($client->getResponse()->getContent());

		self::assertObjectHasAttribute('success', $response);
		self::assertObjectHasAttribute('image', $response);
		self::assertObjectHasAttribute('response', $response);
	}

	public function testUploadException(): void
	{
		$client = static::makeAuthenticatedClient();

		$client->request('POST', '/api/temp_uploaded_files/upload');

		$response = Json::decode($client->getResponse()->getContent());

		$attribute = '@type';

		self::assertObjectHasAttribute($attribute, $response);
		self::assertEquals('hydra:Error', $response->$attribute);
	}

	public function testWebcamUploadAction(): void
	{
		$client = static::makeAuthenticatedClient();

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
			'extension',
			'description',
//			'legacyExtension'
		];

		foreach ($propertiesToCheck as $property) {
			self::assertObjectHasAttribute($property, $response);
		}

		self::assertEquals(filesize($file), $response->size);
		self::assertEquals('image/png', $response->mimetype);
		self::assertEquals('webcam.png', $response->originalFilename);
		self::assertEquals('png', $response->extension);
		self::assertEquals('tempfile', $response->type);

		$property = '@type';
		self::assertEquals('TempUploadedFile', $response->$property);
	}

	public function testGetFile(): void
	{
	}
}
