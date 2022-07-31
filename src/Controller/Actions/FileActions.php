<?php

namespace Limas\Controller\Actions;

use Doctrine\ORM\EntityManagerInterface;
use Gaufrette\Exception\FileNotFound;
use Limas\Entity\UploadedFile;
use Limas\Service\MimetypeIconService;
use Limas\Service\UploadedFileService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class FileActions
	extends AbstractController
{
	public function __construct(
		protected readonly EntityManagerInterface $entityManager,
		protected readonly UploadedFileService    $uploadedFileService,
		protected readonly MimetypeIconService    $mimetypeIconService,
		protected readonly LoggerInterface        $logger,
		protected readonly array                  $limas
	)
	{
	}

	public function getMimeTypeIconAction(Request $request, int $id): Response
	{
		return new BinaryFileResponse(
			$this->mimetypeIconService->getMimetypeIcon($this->entityManager->find($this->getEntityClass($request), $id)->getMimetype()),
			Response::HTTP_OK,
			[],
			false,
			null,
			true,
			true
		);
	}

	public function getFileAction(Request $request, int $id): Response
	{
		/** @var UploadedFile $file */
		$file = $this->entityManager->find($this->getEntityClass($request), $id);
		try {
			return new Response($this->uploadedFileService->getStorage($file)->read($file->getFullFilename()), Response::HTTP_OK, ['Content-Type' => $file->getMimeType()]);
		} catch (FileNotFound $e) {
			$this->logger->error(sprintf('File %s not found in storage %s', $file->getFullFilename(), $file->getType()));
			return new Response('404 File not found', Response::HTTP_NOT_FOUND);
		}
	}

	protected function getEntityClass(Request $request): string
	{
		return $request->attributes->get('_api_resource_class');
	}
}
