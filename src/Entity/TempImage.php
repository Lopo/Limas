<?php

namespace Limas\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use Limas\Controller\Actions\ImageActions;
use Limas\Controller\Actions\TemporaryImageActions;
use Limas\Repository\TempImageRepository;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: TempImageRepository::class)]
#[ApiResource(
	collectionOperations: [
		'upload' => [
			'method' => 'post',
			'path' => 'temp_images/upload',
			'controller' => TemporaryImageActions::class . '::uploadAction',
			'deserialize' => false,
			'output_formats' => [
				'json'
			]
		],
		'webcamUpload' => [
			'method' => 'post',
			'path' => 'temp_images/webcamUpload',
			'controller' => TemporaryImageActions::class . '::webcamUploadAction',
			'deserialize' => false
		]
	],
	itemOperations: [
		'get',
		'getImage' => [
			'method' => 'get',
			'path' => 'temp_images/{id}/getImage',
			'controller' => ImageActions::class . '::getImageAction'
		]
	]
)]
class TempImage
	extends Image
{
	public function __construct()
	{
		parent::__construct(Image::IMAGE_TEMP);
	}
}
