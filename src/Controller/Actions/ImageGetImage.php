<?php

namespace Limas\Controller\Actions;

use Gaufrette\Exception\FileNotFound;
use Limas\Response\ImageResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;


#[AsController]
class ImageGetImage
	extends ImageActions
{
	public function __invoke(Request $request, int $id, LoggerInterface $logger): ImageResponse|Response
	{
		$image = $this->entityManager->find($this->getEntityClass($request), $id);
		$width = $request->get('maxWidth', 200);
		$height = $request->get('maxHeight', 200);
		if ($image === null) {
			return new ImageResponse($width, $height, Response::HTTP_NOT_FOUND, '404 not found');
		}
		try {
			$file = $this->fitWithin($image, $width, $height);
		} catch (FileNotFound $e) {
			$logger->error($e->getMessage());
			return new ImageResponse($width, $height, Response::HTTP_NOT_FOUND, '404 not found');
		} catch (\Exception $e) {
			$logger->error($e->getMessage());
			return new ImageResponse($width, $height, Response::HTTP_INTERNAL_SERVER_ERROR, '500 Server Error');
		}

		return new Response(file_get_contents($file), Response::HTTP_OK, ['Content-Type' => 'image/png']);
	}
}
