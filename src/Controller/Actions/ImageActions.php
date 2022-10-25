<?php

namespace Limas\Controller\Actions;

use Doctrine\ORM\EntityManagerInterface;
use Gaufrette\Exception\FileNotFound;
use Imagine\Image\AbstractImagine;
use Imagine\Image\Box;
use Imagine\Image\Point;
use Limas\Entity\CachedImage;
use Limas\Entity\UploadedFile;
use Limas\Service\MimetypeIconService;
use Limas\Service\UploadedFileService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class ImageActions
	extends FileActions
{
	public function __construct(
		EntityManagerInterface                   $entityManager,
		UploadedFileService                      $uploadedFileService,
		MimetypeIconService                      $mimetypeIconService,
		LoggerInterface                          $logger,
		array                                    $limas,

		protected readonly AbstractImagine       $liipImagine
	)
	{
		parent::__construct($entityManager, $uploadedFileService, $mimetypeIconService, $logger, $limas);
	}

	public function getImageAction(Request $request, int $id, LoggerInterface $logger): Response
	{
		$image = $this->entityManager->find($this->getEntityClass($request), $id);
		$width = $request->get('maxWidth', 200);
		$height = $request->get('maxHeight', 200);
		if ($image === null) {
			return $this->createImageResponse($width, $height, Response::HTTP_NOT_FOUND, '404 not found');
		}
		try {
			$file = $this->fitWithin($image, $width, $height);
		} catch (FileNotFound $e) {
			$logger->error($e->getMessage());
			return $this->createImageResponse($width, $height, Response::HTTP_NOT_FOUND, '404 not found');
		} catch (\Exception $e) {
			$logger->error($e->getMessage());
			return $this->createImageResponse($width, $height, Response::HTTP_INTERNAL_SERVER_ERROR, '500 Server Error');
		}

		return new Response(file_get_contents($file), Response::HTTP_OK, ['Content-Type' => 'image/png']);
	}

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

		$this->liipImagine
			->open($localCacheFile)
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

	private function createImageResponse(int $maxWidth, int $maxHeight, int $code, string $message): Response
	{
		if (0 === $maxWidth) {
			$maxWidth = 300;
		}
		if (0 === $maxHeight) {
			$maxHeight = 300;
		}

		$image = $this->liipImagine->create(new Box(300, 300));
		$image->draw()->text($message, $this->liipImagine->font(realpath($this->getParameter('kernel.project_dir') . '/public/fonts/OpenSans-Regular.ttf'), 24, $image->palette()->color('000')), new Point(0, 0));

		$box = $image->getSize();
		$box = $box->widen($maxWidth);
		if ($box->getHeight() > $maxHeight) {
			$box = $box->heighten($maxHeight);
		}
		$image->resize($box);

		return new Response($image->get('png'), $code, ['Content-Type' => 'image/png']);
	}
}
