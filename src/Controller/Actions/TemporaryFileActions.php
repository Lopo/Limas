<?php

namespace Limas\Controller\Actions;

use Limas\Entity\TempUploadedFile;
use Limas\Response\TemporaryImageUploadResponse;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;


class TemporaryFileActions
	extends FileActions
{
	public function uploadAction(Request $request, TranslatorInterface $translator, NormalizerInterface $normalizer, array $limas): JsonResponse
	{
		$uploadedFile = new TempUploadedFile;
		if (null !== ($file = $request->files->get('userfile'))) {
			if (!$file->isValid()) {
				$error = match ($file->getError()) {
					UPLOAD_ERR_INI_SIZE => $translator->trans('The uploaded file is too large.'),
					default => $translator->trans('Unknown error, error code %code', ['code' => $file->getError()]),
				};
				throw new \RuntimeException($error);
			}
			if (isset($limas['upload']['limit'])
				&& $limas['upload']['limit'] !== false
				&& $file->getSize() > (int)$limas['upload']['limit']
			) {
				throw new \RuntimeException($translator->trans('The uploaded file is too large.'));
			}
			$this->uploadedFileService->replace($uploadedFile, new File($file->getPathname()));
			$uploadedFile->setOriginalFilename($file->getClientOriginalName());
		} elseif (null !== ($url = $request->request->get('url'))) {
			try {
				$this->uploadedFileService->replaceFromURL($uploadedFile, $url, $request->headers);
			} catch (\RuntimeException $e) {
				// Only stash URL-only fallbacks for transient/anti-bot
				// failures — 404 / 410 / 401 / etc. won't get better with a
				// daily cron retry, surface those as real errors so the user
				// notices the dead URL and the queue doesn't fill with junk.
				if (!$this->uploadedFileService->isRecoverableDownloadError($e)) {
					throw $e;
				}
				$this->uploadedFileService->saveUrlOnly($uploadedFile, $url);
			}
		} else {
			throw new \RuntimeException($translator->trans('No valid file given'));
		}

		if (null !== ($description = $request->request->get('description'))) {
			$uploadedFile->setDescription($description);
		}

		$this->entityManager->persist($uploadedFile);
		$this->entityManager->flush();

		return new JsonResponse(new TemporaryImageUploadResponse($normalizer->normalize($uploadedFile, 'jsonld', [])));
	}

	public function webcamUploadAction(Request $request): TempUploadedFile
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
