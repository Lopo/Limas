<?php

namespace Limas\Entity;

use ApiPlatform\Action\NotFoundAction;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use Limas\Controller\Actions\ImageGetImage;
use Limas\Controller\Actions\TempImageUpload;
use Limas\Controller\Actions\TempImageWebcamUpload;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity]
#[ApiResource(
	operations: [
		new GetCollection(controller: NotFoundAction::class, output: false, read: false),
		new Post(uriTemplate: '/temp_images/upload', outputFormats: ['json'], controller: TempImageUpload::class, deserialize: false),
		new Post(uriTemplate: '/temp_images/webcamUpload', controller: TempImageWebcamUpload::class, deserialize: false),

		new Get(),
		new Get(uriTemplate: '/temp_images/{id}/getImage', controller: ImageGetImage::class)
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
