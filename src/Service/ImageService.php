<?php

namespace Limas\Service;

use Limas\Entity\CachedImage;
use Limas\Entity\UploadedFile;
use Symfony\Component\HttpFoundation\File\File;


class ImageService
	extends UploadedFileService
{
	public function replaceFromFilesystem(UploadedFile $file, File $filesystemFile): void
	{
		parent::replaceFromFilesystem($file, $filesystemFile);
		$this->invalidate($file);
	}

	public function invalidate(UploadedFile $file): void
	{
		foreach ($this->entityManager->getRepository(CachedImage::class)->findBy(['originalId' => $file->getId(), 'originalType' => $file->getType()]) as $cimg) {
			if (file_exists($cimg->getCacheFile())) {
				unlink($cimg->getCacheFile());
			}
			$this->entityManager->remove($cimg);
		}
	}

	public function canHandleMimetype(string $mimeType): bool
	{
		return match ($mimeType) {
			'image/jpeg', 'image/png', 'image/gif' => true,
			default => false,
		};
	}
}
