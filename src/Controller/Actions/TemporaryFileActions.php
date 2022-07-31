<?php

namespace Limas\Controller\Actions;

use Limas\Entity\TempUploadedFile;
use Limas\Response\TemporaryImageUploadResponse;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;


class TemporaryFileActions
	extends FileActions
{
	public function uploadAction(Request $request, TranslatorInterface $translator, SerializerInterface $serializer, array $limas): JsonResponse
	{
		$uploadedFile = new TempUploadedFile;
		if (null !== ($file = $request->files->get('userfile'))) {
			if (!$file->isValid()) {
				$error = match ($file->getError()) {
					UPLOAD_ERR_INI_SIZE => $translator->trans('The uploaded file is too large.'),
					default => $translator->trans('Unknown error, error code %code', ['code' => $file->getError()]),
				};
				throw new \Exception($error);
			}
			if (isset($limas['upload']['limit'])
				&& $limas['upload']['limit'] !== false
				&& $file->getSize() > (int)$limas['upload']['limit']
			) {
				throw new \Exception($translator->trans('The uploaded file is too large.'));
			}
			$this->uploadedFileService->replace($uploadedFile, new File($file->getPathname()));
			$uploadedFile->setOriginalFilename($file->getClientOriginalName());
		} elseif (null !== ($url = $request->request->get('url'))) {
			$this->uploadedFileService->replaceFromURL($uploadedFile, $url);
		} else {
			throw new \Exception($translator->trans('No valid file given'));
		}

		if (null !== ($description = $request->request->get('description'))) {
			$uploadedFile->setDescription($description);
		}

		$this->entityManager->persist($uploadedFile);
		$this->entityManager->flush();

		$serializedData = $serializer->normalize($uploadedFile, 'jsonld', []);
		return new JsonResponse(new TemporaryImageUploadResponse($serializedData));
	}

	public function webcamUploadAction(Request $request): TempUploadedFile
	{
		$file = new TempUploadedFile;
		$data = $request->getContent();

		$base64 = explode(',', $data);
		$this->uploadedFileService->replaceFromData($file, base64_decode($base64[1], true), 'webcam.png');

		$this->entityManager->persist($file);
		$this->entityManager->flush();

		return $file;
	}

	protected function getEntityClass(Request $request): string
	{
		return TempUploadedFile::class;
	}
}
