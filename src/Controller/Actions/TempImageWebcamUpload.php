<?php

namespace Limas\Controller\Actions;

use Limas\Entity\TempImage;
use Limas\Service\ImageService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;


#[AsController]
class TempImageWebcamUpload
	extends ImageActions
{
	public function __invoke(Request $request, ImageService $imageService): TempImage
	{
		$image = new TempImage;
		$data = $request->getContent();

		$base64 = explode(',', $data);
		$imageService->replaceFromData($image, base64_decode($base64[1], true), 'webcam.png');

		$this->entityManager->persist($image);
		$this->entityManager->flush();

		return $image;
	}
}
