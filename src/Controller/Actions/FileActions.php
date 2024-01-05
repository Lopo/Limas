<?php

namespace Limas\Controller\Actions;

use ApiPlatform\Doctrine\Orm\State\ItemProvider;
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
	use ActionUtilTrait;


	public function __construct(
		protected readonly EntityManagerInterface $entityManager,
		protected readonly UploadedFileService    $uploadedFileService,
		protected readonly MimetypeIconService    $mimetypeIconService,
		protected readonly LoggerInterface        $logger,
		protected readonly ItemProvider           $dataProvider,
		protected readonly array                  $limas
	)
	{
	}

	public function getMimeTypeIconAction(Request $request, int $id): Response
	{
		$entity = $this->getItem($this->dataProvider, $this->getEntityClass($request), $id);
		return new BinaryFileResponse(
			$this->mimetypeIconService->getMimetypeIcon($entity->getMimetype()),
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
		$file = $this->getItem($this->dataProvider, $this->getEntityClass($request), $id);
		try {
			return new Response($this->uploadedFileService->getStorage($file)->read($file->getFilename()), Response::HTTP_OK, ['Content-Type' => $file->getMimetype()]);
		} catch (FileNotFound $e) {
			$this->logger->error(sprintf('File %s not found in storage %s', $file->getFilename(), $file->getType()));
			return new Response('404 File not found', Response::HTTP_NOT_FOUND);
		}
	}

	public function deleteFileAction(Request $request, int $id): object
	{
		try {
			/** @var UploadedFile $file */
			$file = $this->getItem($this->dataProvider, $this->getEntityClass($request), $id);
			$this->uploadedFileService->getStorage($file)->delete($file->getFilename());
			$this->entityManager->remove($file);
			return $file;
		} catch (\Throwable $e) {
			return new Response('', Response::HTTP_INTERNAL_SERVER_ERROR);
		}
	}

	protected function getEntityClass(Request $request): string
	{
		return $request->attributes->get('_api_resource_class');
	}
}
