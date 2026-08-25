<?php

namespace Limas\Command;

use Limas\Service\UploadedFileService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;


/**
 * Manual trigger for the temp-upload purge. The same logic runs on a schedule
 * via {@see \Limas\MessageHandler\PurgeTempUploadsMessageHandler}
 * (see {@see \Limas\Scheduler\LimasSchedule}) — this is the "do it now" entry.
 *
 * Temp uploads are copied to a permanent attachment on part save and left
 * behind (no FK back to the part), so they accumulate; this drops the ones
 * past the retention window. Removal is refcount-safe (CAS keeps blobs a
 * permanent attachment still points at).
 */
#[AsCommand(
	name: 'limas:attachments:purge-temp',
	description: 'Delete temp upload rows older than the retention window (default from config).'
)]
final class PurgeTempUploadsCommand
	extends Command
{
	public function __construct(
		private readonly UploadedFileService $uploadedFileService,
		private readonly int                 $defaultTtlDays
	)
	{
		parent::__construct();
	}

	protected function configure(): void
	{
		$this
			->addOption('ttl', 't', InputOption::VALUE_REQUIRED, 'Delete temps older than N days.', (string)$this->defaultTtlDays)
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Count candidates without deleting anything.');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);
		$ttl = (int)$input->getOption('ttl');
		$dryRun = $input->getOption('dry-run') === true;

		$stats = $this->uploadedFileService->purgeExpiredTempUploads($ttl, $dryRun);

		if ($dryRun) {
			$io->note(sprintf('Dry run — %d temp upload(s) older than %dd would be deleted.', $stats['considered'], $ttl));
		} else {
			$io->success(sprintf('Deleted %d temp upload(s) older than %dd.', $stats['deleted'], $ttl));
		}

		return Command::SUCCESS;
	}
}
