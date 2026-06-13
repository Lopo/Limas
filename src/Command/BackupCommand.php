<?php

namespace Limas\Command;

use Limas\Service\BackupService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;


/**
 * Manual entry point for the full backup task. Useful right before an
 * upgrade or migration — the scheduled run only fires on its cron
 * cadence (default daily 02:00) which may not align with the moment
 * you actually need a fresh archive.
 */
#[AsCommand(
	name: 'limas:backup:create',
	description: 'Create a full backup archive (DB + attachments + secrets) right now.'
)]
final class BackupCommand
	extends Command
{
	public function __construct(
		private readonly BackupService $backupService
	)
	{
		parent::__construct();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);
		$io->section('Creating Limas backup');
		try {
			$path = $this->backupService->create();
		} catch (\Throwable $e) {
			$io->error('Backup failed: ' . $e->getMessage());
			return self::FAILURE;
		}
		$io->success(sprintf('Backup written to %s', $path));
		return self::SUCCESS;
	}
}
