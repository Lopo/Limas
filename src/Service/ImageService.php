<?php

namespace Limas\Service;

use Doctrine\ORM\EntityManagerInterface;
use Imagine\Image\AbstractImagine;
use League\Flysystem\FilesystemOperator;
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
		FilesystemOperator               $blobStorage,
		EntityManagerInterface           $entityManager,
		array                            $limas,

		private readonly AbstractImagine $liipImagine
	)
	{
		parent::__construct($systemService, $logger, $blobStorage, $entityManager, $limas);
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
		// `getDriverInfo()` is a static method on each concrete driver
		// (Gd/Imagick/Gmagick); calling it through an AbstractImagine
		// reference works via late static binding but phpstan only sees
		// the abstract type.
		// @phpstan-ignore-next-line method.notFound
		foreach ($this->liipImagine->getDriverInfo()->getSupportedFormats()->getAll() as $format) {
			if ($format->getMimeType() === $mimeType) {
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

	/**
	 * Drop the on-disk thumbnail cache dir for this image. Pure file ops,
	 * no em mutations — safe to call from inside a flush event. The
	 * FileRemoval onFlush listener uses this instead of `delete()`
	 * specifically to dodge the nested-flush trap.
	 */
	public function dropThumbnailCache(UploadedFile $file): void
	{
		if (!($file instanceof PartAttachment && $file->isImage())) {
			return;
		}
		// Cache key was built from the (now-CAS) filename via sha1; we
		// can't compute it once the Blob is gone, so the cache wipe
		// happens at onFlush time while the blob FK is still readable.
		$filename = $file->getFilename();
		if ($filename === null) {
			return;
		}
		$path = $this->getCacheDirForImage($file);
		try {
			FileSystem::delete($path);
		} catch (IOException $e) {
			$this->logger->alert($e->getMessage(), [$e, $path]);
		}
	}

	public function delete(UploadedFile $file): void
	{
		// Public "delete this image" facade — drops the thumbnail cache
		// and then routes the entity delete through UploadedFileService
		// which schedules em->remove + flush. The FileRemoval listener
		// fires on that flush and uses dropThumbnailCache() (idempotent),
		// not delete() itself, so no recursion.
		$this->dropThumbnailCache($file);
		parent::delete($file);
	}
}
