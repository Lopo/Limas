<?php

namespace Limas\Controller\Actions;

use ApiPlatform\Core\DataProvider\ItemDataProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Gaufrette\Exception\FileNotFound;
use Imagine\Image\AbstractImagine;
use Imagine\Image\Box;
use Imagine\Image\Point;
use Limas\Entity\CachedImage;
use Limas\Entity\UploadedFile;
use Limas\Service\ImageService;
use Limas\Service\MimetypeIconService;
use Limas\Service\UploadedFileService;
use Nette\Utils\FileSystem;
use Nette\Utils\Strings;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class ImageActions
	extends FileActions
{
	public function __construct(
		EntityManagerInterface             $entityManager,
		UploadedFileService                $uploadedFileService,
		MimetypeIconService                $mimetypeIconService,
		LoggerInterface                    $logger,
		ItemDataProviderInterface          $dataProvider,
		array                              $limas,

		protected readonly ImageService    $imageService,
		protected readonly AbstractImagine $liipImagine
	)
	{
		parent::__construct($entityManager, $uploadedFileService, $mimetypeIconService, $logger, $dataProvider, $limas);
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

		return new Response(FileSystem::read($file), Response::HTTP_OK, ['Content-Type' => 'image/png']);
	}

	public function deleteImageAction(Request $request, int $id): object
	{
		try {
			$image = $this->entityManager->find($this->getEntityClass($request), $id);
			$this->uploadedFileService->getStorage($image)->delete($image->getFullFilename());
			$this->entityManager->remove($image);
			return $image;
		} catch (\Throwable $e) {
			return new Response('', Response::HTTP_INTERNAL_SERVER_ERROR);
		}
	}

	protected function fitWithin(UploadedFile $image, int $width, int $height, bool $padding = false): string
	{
		$mode = $padding ? 'fwp' : 'fw';

		$outputFile = $this->getImageCacheFilename($image, $width, $height, $mode);

		if ($this->hasCacheFile($image, $width, $height, $mode) && file_exists($outputFile)) {
			return $outputFile;
		}

		$localCacheFile = $this->imageService->getCacheDirForImage($image) . $image->getFullFilename();

		FileSystem::write($localCacheFile, $this->uploadedFileService->getStorage($image)->read($image->getFullFilename()));

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
		$path = $this->imageService->getCacheDirForImage($image);
		FileSystem::createDir($path);
		return "$path{$width}x{$height}_$mode.png";
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
		$cacheFile = $this->imageService->getImageCacheDirectory() . "$code-{$maxWidth}x$maxHeight-" . Strings::webalize($message) . '.png';
		if (!file_exists($cacheFile)) {
			$image = $this->liipImagine->create(new Box(300, 300));
			$image->draw()->text($message, $this->liipImagine->font(realpath($this->getParameter('kernel.project_dir') . '/public/fonts/OpenSans-Regular.ttf'), 24, $image->palette()->color('000')), new Point(0, 0));

			$box = $image->getSize();
			$box = $box->widen($maxWidth);
			if ($box->getHeight() > $maxHeight) {
				$box = $box->heighten($maxHeight);
			}
			$image->resize($box);
			FileSystem::write($cacheFile, $content = $image->get('png'));
		} else {
			$content = FileSystem::read($cacheFile);
		}

		return new Response($content, $code, ['Content-Type' => 'image/png']);
	}
}
