<?php

namespace Limas\MessageHandler;

use Limas\Message\BackupMessage;
use Limas\Service\BackupService;
use Limas\Service\SystemNoticeService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;


#[AsMessageHandler]
final readonly class BackupMessageHandler
{
	public function __construct(
		private bool                $enabled,
		private BackupService       $backupService,
		private LoggerInterface     $logger,
		private SystemNoticeService $systemNoticeService
	)
	{
	}

	public function __invoke(BackupMessage $message): void
	{
		if (!$this->enabled) {
			return;
		}
		try {
			$path = $this->backupService->create();
			$this->logger->info('Backup completed', ['path' => $path]);
		} catch (\Throwable $e) {
			$this->logger->error('Backup failed', ['error' => $e->getMessage()]);
			// Operator-facing notice — failures here mean the next disaster
			// won't have a recent restore point, important to surface even
			// if the messenger retry strategy already logged it.
			$this->systemNoticeService->createUniqueSystemNotice(
				'LIMAS_BACKUP_FAIL_' . (new \DateTimeImmutable)->format('Ymd'),
				'Limas backup failed',
				'The scheduled backup task could not complete: ' . $e->getMessage()
			);
		}
	}
}
