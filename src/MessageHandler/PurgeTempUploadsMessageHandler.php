<?php

namespace Limas\MessageHandler;

use Limas\Message\PurgeTempUploadsMessage;
use Limas\Service\UploadedFileService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;


#[AsMessageHandler]
final readonly class PurgeTempUploadsMessageHandler
{
	public function __construct(
		private UploadedFileService $uploadedFileService,
		private LoggerInterface     $logger,
		private int                 $ttlDays
	)
	{
	}

	public function __invoke(PurgeTempUploadsMessage $message): void
	{
		$stats = $this->uploadedFileService->purgeExpiredTempUploads($this->ttlDays);
		$this->logger->info('Temp upload purge: {deleted} deleted (older than {ttl}d)', $stats + ['ttl' => $this->ttlDays]);
	}
}
