<?php

namespace Limas\Tests;

use ApiPlatform\Api\IriConverterInterface;
use Liip\FunctionalTestBundle\Test\WebTestCase;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Limas\Service\ImageService;
use Limas\Tests\DataFixtures\UserDataLoader;
use Nette\Utils\Json;
use Symfony\Component\HttpFoundation\File\UploadedFile;


class ImageControllerTest
	extends WebTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		static::getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
			UserDataLoader::class
		])->getReferenceRepository();
	}

	public function testGetImage(): void
	{
		$client = static::makeAuthenticatedClient();

		$client->request(
			'POST',
			'/api/temp_images/upload',
			[],
			['userfile' => new UploadedFile(
				__DIR__ . '/DataFixtures/files/uploadtest.png',
				'uploadtest.png',
				'image/png',
				null,
				true
			)]
		);

		$response = Json::decode($client->getResponse()->getContent());

		$imageId = $response->image->{'@id'};
		$uri = $imageId . '/getImage';

		$client->request('GET', $uri);

		self::assertEquals('image/png', $client->getResponse()->headers->get('Content-Type'));

		$imageSize = getimagesizefromstring($client->getResponse()->getContent());

		self::assertEquals(51, $imageSize[0]);
		self::assertEquals(23, $imageSize[1]);

		$this->getContainer()->get(ImageService::class)->delete($this->getContainer()->get(IriConverterInterface::class)->getResourceFromIri($imageId));

		$client->request('GET', $uri);

		self::assertEquals(404, $client->getResponse()->getStatusCode());
	}
}
