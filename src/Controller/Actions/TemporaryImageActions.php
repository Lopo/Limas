<?php

namespace Limas\Controller\Actions;

use Limas\Entity\TempImage;
use Limas\Response\TemporaryImageUploadResponse;
use Limas\Service\ImageService;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;


class TemporaryImageActions
	extends ImageActions
{
	public function uploadAction(Request $request, ImageService $imageService, NormalizerInterface $normalizer): JsonResponse
	{
		$image = new TempImage;
		if (null !== ($file = $request->files->get('userfile'))) {
			$imageService->replace($image, new File($file->getPathname()));
			$image->setOriginalFilename($file->getClientOriginalName());
		} elseif (null !== ($url = $request->request->get('url'))) {
			$imageService->replaceFromURL($image, $url, $request->headers);
		} else {
			throw new \RuntimeException('Error: No valid file given');
		}

		$this->entityManager->persist($image);
		$this->entityManager->flush();

		$serializedData = $normalizer->normalize($image, 'jsonld', []);

		return new JsonResponse(new TemporaryImageUploadResponse($serializedData));
	}

	public function webcamUploadAction(Request $request, ImageService $imageService): TempImage
	{
		$image = new TempImage;

		$base64 = explode(',', $request->getContent());
		$imageService->replaceFromData($image, base64_decode($base64[1], true), 'webcam.png');

		$this->entityManager->persist($image);
		$this->entityManager->flush();

		return $image;
	}
}
