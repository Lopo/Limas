<?php

namespace Limas\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use Doctrine\ORM\Mapping as ORM;
use Limas\Controller\Actions\ImageActions;
use Limas\Controller\Actions\TemporaryImageActions;


#[ORM\Entity]
#[ApiResource(
	operations: [
		new Post(
			uriTemplate: 'temp_images/upload',
			outputFormats: ['json'],
			controller: TemporaryImageActions::class . '::uploadAction',
			deserialize: false,
			name: 'TemporaryImageUpload'
		),
		new Post(
			uriTemplate: 'temp_images/webcamUpload',
			controller: TemporaryImageActions::class . '::webcamUploadAction',
			deserialize: false,
			name: 'TemporaryImageUploadWebcam'
		),
		new Get,
		new Get(
			uriTemplate: 'temp_images/{id}/getImage',
			controller: ImageActions::class . '::getImageAction',
			name: 'TemporaryImageGet'
		),
		new Delete(
			controller: ImageActions::class . '::deleteImageAction'
		)
	],
	normalizationContext: ['groups' => ['default']],
	denormalizationContext: ['groups' => ['default']]
)]
class TempImage
	extends Image
{
	public function __construct()
	{
		parent::__construct(Image::IMAGE_TEMP);
	}
}
