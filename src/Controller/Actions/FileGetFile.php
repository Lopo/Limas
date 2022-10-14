<?php

namespace Limas\Controller\Actions;

use Gaufrette\Exception\FileNotFound;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;


#[AsController]
class FileGetFile
	extends FileActions
{
	public function __invoke(Request $request, int $id): Response
	{
		$file = $this->entityManager->find($this->getEntityClass($request), $id);
		try {
			return new Response($this->uploadedFileService->getStorage($file)->read($file->getFullFilename()), Response::HTTP_OK, ['Content-Type' => $file->getMimetype()]);
		} catch (FileNotFound $e) {
			$this->logger->error(sprintf('File %s not found in storage %s', $file->getFullFilename(), $file->getType()));
			return new Response('404 File not found', Response::HTTP_NOT_FOUND);
		}
	}
}
