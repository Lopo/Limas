<?php

namespace Limas\Tests;

use Doctrine\Common\DataFixtures\ReferenceRepository;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Limas\Tests\DataFixtures\UserDataLoader;
use Nette\Utils\Json;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;


class FileControllerTest
	extends WebTestCase
{
	protected ReferenceRepository $fixtures;


	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		$fs = new Filesystem;
		$fs->mkdir(self::getContainer()->getParameter('limas')['filesystem']['data_directory']);
	}

	protected function setUp(): void
	{
		parent::setUp();
		$this->fixtures = self::getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
			UserDataLoader::class
		])->getReferenceRepository();
	}

	public function testMimeType(): void
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

		self::assertObjectHasProperty('image', $response);

		$client->request('GET', $response->image->{'@id'} . '/getMimeTypeIcon');

		self::assertEquals('image/svg+xml', $client->getResponse()->headers->get('Content-Type'));
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

		$client->request('GET', $response->image->{'@id'} . '/getFile');

		self::assertEquals('image/png', $client->getResponse()->headers->get('Content-Type'));
		self::assertStringEqualsFile($file, $client->getResponse()->getContent());
	}
}
