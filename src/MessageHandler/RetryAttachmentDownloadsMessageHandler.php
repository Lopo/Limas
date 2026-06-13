<?php

namespace Limas\MessageHandler;

use Limas\Message\RetryAttachmentDownloadsMessage;
use Limas\Service\UploadedFileService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;


#[AsMessageHandler]
final readonly class RetryAttachmentDownloadsMessageHandler
{
	public function __construct(
		private UploadedFileService $uploadedFileService,
		private LoggerInterface     $logger
	)
	{
	}

	public function __invoke(RetryAttachmentDownloadsMessage $message): void
	{
		$stats = $this->uploadedFileService->retryPendingDownloads();
		$this->logger->info('Attachment retry pass: {pending} pending → {ok} ok, {fail} failed', $stats);
	}
}
