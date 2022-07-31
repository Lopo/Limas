<?php

namespace Limas\Service;

use Doctrine\ORM\EntityManagerInterface;
use Gaufrette\Exception\FileNotFound;
use Gaufrette\Filesystem;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Knp\Bundle\GaufretteBundle\FilesystemMap;
use Limas\Entity\UploadedFile;
use Limas\Exceptions\DiskSpaceExhaustedException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\HttpFoundation\File\File;


class UploadedFileService
{
	public function __construct(
		protected readonly ContainerBagInterface  $container,
		private readonly SystemService            $systemService,
		private readonly LoggerInterface          $logger,
		private readonly FilesystemMap            $filesystem,
		protected readonly EntityManagerInterface $entityManager,
		protected readonly array                  $limas
	)
	{
	}

	public function replace(UploadedFile $file, File $filesystemFile): void
	{
		$this->replaceFromFilesystem($file, $filesystemFile);
	}

	public function replaceFromFilesystem(UploadedFile $file, File $filesystemFile): void
	{
		$file->setOriginalFilename($filesystemFile->getBasename());
		$file->setExtension($filesystemFile->getExtension());
		$file->setMimeType($filesystemFile->getMimeType());
		$file->setSize($filesystemFile->getSize());

		$storage = $this->getStorage($file);

		if ($filesystemFile->getSize() > $this->systemService->getFreeDiskSpace()) {
			throw new DiskSpaceExhaustedException;
		}

		$storage->write($file->getFullFilename(), file_get_contents($filesystemFile->getPathname()), true);
	}

	public function replaceFromData(UploadedFile $file, $data, $filename): void
	{
		$tmpdir = ini_get('upload_tmp_dir') ?: sys_get_temp_dir();
		$tempName = tempnam($tmpdir, 'LIMAS');

		file_put_contents($tempName, $data);

		$this->replaceFromFilesystem($file, new File($tempName));
		$file->setOriginalFilename($filename);

		unlink($tempName);
	}

	public function delete(UploadedFile $file): void
	{
		$storage = $this->getStorage($file);

		try {
			$storage->delete($file->getFullFilename());
			$this->entityManager->remove($file);
			$this->entityManager->flush();
		} catch (FileNotFound $e) {
			$this->logger->alert(sprintf('Unable to delete file %s', $file->getFullFilename()), [$e, $file]);
		}
	}

	public function replaceFromUploadedFile(UploadedFile $target, UploadedFile $source): void
	{
		$storage = $this->getStorage($source);
		$this->replaceFromData($target, $storage->read($source->getFullFilename()), $source->getFullFilename());
		$target->setOriginalFilename($source->getOriginalFilename());
	}

	public function replaceFromURL(UploadedFile $file, string $url): void
	{
		try {
			$data = (new Client)
				->request('GET', $url, [
					RequestOptions::TIMEOUT => 30,
					RequestOptions::ALLOW_REDIRECTS => [
						'max' => 5,
						'referer' => true
					],
					RequestOptions::HEADERS => [
						'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36',
						'Accept' => 'text/xml,application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5',
						'Cache-Control' => 'max-age=0',
						'Connection' => 'keep-alive',
						'Keep-Alive' => '300',
						'Accept-Charset' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.7',
						'Accept-Language' => 'en-us,en;q=0.5',
						'Pragma' => ''
					]
				])
				->getBody();
		} catch (GuzzleException $e) {
			throw new \Exception('replaceFromURL error: ' . str_replace(['>', '<'], '', $e->getMessage()), $e->getCode(), $e);
		}

		$this->replaceFromData($file, $data, basename($url));
	}

	public function getStorageDirectory(UploadedFile $file): string
	{
		return $this->limas['directories'][$file->getType()];
	}

	public function getStorage(UploadedFile $file): Filesystem
	{
		return $this->filesystem->get(strtolower($file->getType()));
	}
}
