<?php

namespace Limas\Tests;

use ApiPlatform\Api\IriConverterInterface;
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
		self::getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
			UserDataLoader::class
		])->getReferenceRepository();
	}

	public function testGetImage(): void
	{
		$client = $this->makeAuthenticatedClient();

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

		$container = self::getContainer();
		$container->get(ImageService::class)->delete($container->get(IriConverterInterface::class)->getResourceFromIri($imageId));

		$client->request('GET', $uri);

		self::assertEquals(404, $client->getResponse()->getStatusCode());
	}
}
