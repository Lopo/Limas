<?php

namespace Limas\Service;

use Doctrine\ORM\EntityManagerInterface;
use Imagine\Image\AbstractImagine;
use Knp\Bundle\GaufretteBundle\FilesystemMap;
use Limas\Entity\CachedImage;
use Limas\Entity\PartAttachment;
use Limas\Entity\UploadedFile;
use Nette\IOException;
use Nette\Utils\FileSystem;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\File;


class ImageService
	extends UploadedFileService
{
	public function __construct(
		SystemService                    $systemService,
		LoggerInterface                  $logger,
		FilesystemMap                    $filesystem,
		EntityManagerInterface           $entityManager,
		array                            $limas,

		private readonly AbstractImagine $liipImagine
	)
	{
		parent::__construct($systemService, $logger, $filesystem, $entityManager, $limas);
	}

	public function replaceFromFilesystem(UploadedFile $file, File $filesystemFile): void
	{
		parent::replaceFromFilesystem($file, $filesystemFile);
		$this->invalidate($file);
	}

	public function invalidate(UploadedFile $file): void
	{
		foreach ($this->entityManager->getRepository(CachedImage::class)->findBy(['originalId' => $file->getId(), 'originalType' => $file->getType()]) as $cimg) {
			FileSystem::delete($cimg->getCacheFile());
			$this->entityManager->remove($cimg);
		}
	}

	public function canHandleMimetype(string $mimeType): bool
	{
		foreach ($this->liipImagine->getDriverInfo()->getSupportedFormats()->getAll() as $format) {
			if ($format->getCanonicalFileExtension() === $mimeType) {
				return true;
			}
		}
		return false;
	}

	public function getCacheDirForImage(UploadedFile $image): string
	{
		return $this->getImageCacheDirectory() . sha1($image->getFilename()) . '/';
	}

	public function getImageCacheDirectory(): string
	{
		return $this->limas['image_cache_directory'];
	}

	public function delete(UploadedFile $file): void
	{
		if ($file instanceof PartAttachment && $file->isImage()) {
			$path = $this->getCacheDirForImage($file);
			try {
				FileSystem::delete($path);
			} catch (IOException $e) {
				$this->logger->alert($e->getMessage(), [$e, $path]);
			}
		}
		parent::delete($file);
	}
}
