<?php

namespace Limas\Controller\Actions;

use Limas\Entity\TempUploadedFile;
use Limas\Response\TemporaryImageUploadResponse;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;


#[AsController]
class TempUploadedFileUpload
	extends FileActions
{
	public function __invoke(Request $request, TranslatorInterface $translator, SerializerInterface $serializer): JsonResponse
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
			if (isset($this->limas['upload']['limit'])
				&& $this->limas['upload']['limit'] !== false
				&& $file->getSize() > (int)$this->limas['upload']['limit']
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

		return new JsonResponse(new TemporaryImageUploadResponse($serializer->normalize($uploadedFile, 'jsonld', [])));
	}

	protected function getEntityClass(Request $request): string
	{
		return TempUploadedFile::class;
	}
}
