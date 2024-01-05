<?php

namespace Limas\Service;

use Doctrine\ORM\EntityManagerInterface;
use Gaufrette\Exception\FileNotFound;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Knp\Bundle\GaufretteBundle\FilesystemMap;
use Limas\Entity\UploadedFile;
use Limas\Exceptions\DiskSpaceExhaustedException;
use Nette\Utils\FileSystem;
use Nette\Utils\Strings;
use Nette\Utils\Validators;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints\Hostname;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Validation;


class UploadedFileService
{
	public function __construct(
		private readonly SystemService            $systemService,
		protected readonly LoggerInterface        $logger,
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

	/**
	 * @throws DiskSpaceExhaustedException
	 */
	public function replaceFromFilesystem(UploadedFile $file, File $filesystemFile): void
	{
		if ($filesystemFile->getSize() > $this->systemService->getFreeDiskSpace()) {
			throw new DiskSpaceExhaustedException;
		}

		$file->setOriginalFilename($filesystemFile->getBasename());
		$file->setMimetype($filesystemFile->getMimeType());
		$file->setSize($filesystemFile->getSize());

		$storage = $this->getStorage($file);

		$storage->write($file->getFilename(), FileSystem::read($filesystemFile->getPathname()), true);
	}

	public function replaceFromData(UploadedFile $file, $data, $filename): void
	{
		$tempName = tempnam(ini_get('upload_tmp_dir') ?? sys_get_temp_dir(), 'LIMAS');

		FileSystem::write($tempName, $data);

		$this->replaceFromFilesystem($file, new File($tempName));
		$file->setOriginalFilename($filename);

		FileSystem::delete($tempName);
	}

	public function delete(UploadedFile $file): void
	{
		$storage = $this->getStorage($file);

		try {
			$storage->delete($file->getFilename());
			$this->entityManager->remove($file);
			$this->entityManager->flush();
		} catch (FileNotFound $e) {
			$this->logger->alert(sprintf('Unable to delete file %s', $file->getFilename()), [$e, $file]);
		}
	}

	public function replaceFromUploadedFile(UploadedFile $target, UploadedFile $source): void
	{
		$storage = $this->getStorage($source);
		$this->replaceFromData($target, $storage->read($source->getFilename()), $source->getFilename());
		$target->setOriginalFilename($source->getOriginalFilename());
	}

	public function replaceFromURL(UploadedFile $file, string $url): void
	{
		try {
			Validators::assert($url, 'url');
			Validation::createCallable(new Url)($url);
			Validation::createCallable(new NotBlank, new Hostname)($host = rawurldecode(parse_url($url)['host'] ?? ''));
			if (in_array($host, ['localhost, 127.0.0.1', '::1'], true)) {
				throw new \InvalidArgumentException('Cannot upload files from localhost');
			}

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
			throw new \RuntimeException('replaceFromURL error: ' . str_replace(['>', '<'], '', $e->getMessage()), $e->getCode(), $e);
		}

		$this->replaceFromData($file, $data, basename($url));
	}

	public function getStorageDirectory(UploadedFile $file): string
	{
		return $this->limas['directories'][$file->getType()];
	}

	public function getStorage(UploadedFile $file): \Gaufrette\Filesystem
	{
		return $this->filesystem->get(Strings::lower($file->getType()));
	}
}
