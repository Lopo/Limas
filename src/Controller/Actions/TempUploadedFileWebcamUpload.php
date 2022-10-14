<?php

namespace Limas\Controller\Actions;

use Limas\Entity\TempUploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;


#[AsController]
class TempUploadedFileWebcamUpload
	extends FileActions
{
	public function __invoke(Request $request): TempUploadedFile
	{
		$file = new TempUploadedFile;

		$this->uploadedFileService->replaceFromData($file, base64_decode(explode(',', $request->getContent())[1], true), 'webcam.png');

		$this->entityManager->persist($file);
		$this->entityManager->flush();

		return $file;
	}

	protected function getEntityClass(Request $request): string
	{
		return TempUploadedFile::class;
	}
}
