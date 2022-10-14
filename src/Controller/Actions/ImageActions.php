<?php

namespace Limas\Controller\Actions;

use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Limas\Entity\CachedImage;
use Limas\Entity\UploadedFile;


abstract class ImageActions
	extends FileActions
{
	protected function getImageCacheDirectory(): string
	{
		return $this->limas['image_cache_directory'];
	}

	protected function ensureCacheDirExists(): void
	{
		if (!is_dir($this->getImageCacheDirectory())) {
			mkdir($this->getImageCacheDirectory(), 0777, true);
		}
	}

	protected function fitWithin(UploadedFile $image, int $width, int $height, bool $padding = false): string
	{
		$this->ensureCacheDirExists();

		$mode = $padding ? 'fwp' : 'fw';

		$outputFile = $this->getImageCacheFilename($image, $width, $height, $mode);

		if ($this->hasCacheFile($image, $width, $height, $mode) && file_exists($outputFile)) {
			return $outputFile;
		}

		$localCacheFile = $this->getImageCacheDirectory() . $image->getFullFilename();

		file_put_contents($localCacheFile, $this->uploadedFileService->getStorage($image)->read($image->getFullFilename()));

		(new Imagine)->open($localCacheFile)
			->thumbnail(new Box($width, $height))
			->save($outputFile);

		$this->entityManager->persist(new CachedImage($image, $outputFile));
		$this->entityManager->flush();

		return $outputFile;
	}

	protected function getImageCacheFilename(UploadedFile $image, int $width, int $height, string $mode): string
	{
		return $this->getImageCacheDirectory()
			/*. '/' */ . sha1($image->getFilename())
			. $width . 'x' . $height . '_' . $mode . '.png';
	}

	protected function hasCacheFile(UploadedFile $image, $width, $height, $mode): bool
	{
		$queryBuilder = $this->entityManager->createQueryBuilder();
		return $queryBuilder->select($queryBuilder->expr()->count('c'))
				->from(CachedImage::class, 'c')
				->where($queryBuilder->expr()->eq('c.cacheFile', ':file'))
				->setParameter('file', $this->getImageCacheFilename($image, $width, $height, $mode))
				->getQuery()->getSingleScalarResult() > 0;
	}
}
