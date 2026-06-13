<?php

namespace Limas\Command;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Entity\PartAttachment;
use Limas\Entity\ProjectAttachment;
use Limas\Service\UploadedFileService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;


/**
 * Manual trigger for the URL-only-attachment retry pass
 * The same logic runs on a schedule via `RetryAttachmentDownloadsMessageHandler` (see {@see \Limas\Scheduler\LimasSchedule}) — this command is the "I want it now, don't wait for the next tick" admin entry point
 *
 * Mirrors Part-DB's `partdb:attachments:download` (Part-DB-server/src/Command/Attachments/DownloadAttachmentsCommand.php) but without their batch upload
 * machinery — we just call back into UploadedFileService::retryPendingDownloads
 */
#[AsCommand(
	name: 'limas:attachments:retry-downloads',
	description: 'Retry server-side download for attachments persisted URL-only.'
)]
final class RetryAttachmentDownloadsCommand
	extends Command
{
	public function __construct(
		private readonly EntityManagerInterface $em,
		private readonly UploadedFileService    $uploadedFileService
	)
	{
		parent::__construct();
	}

	protected function configure(): void
	{
		$this
			->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Stop after N successful retries (0 = unlimited).', 0)
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'List candidates without attempting any download.');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);
		$limit = (int)$input->getOption('limit');
		$dryRun = $input->getOption('dry-run') === true;

		if ($dryRun) {
			$rows = [];
			foreach ([PartAttachment::class, ProjectAttachment::class] as $class) {
				$found = $this->em->getRepository($class)->createQueryBuilder('a')
					->where('a.blob IS NULL')
					->andWhere('a.sourceUrl IS NOT NULL')
					->getQuery()
					->getResult();
				foreach ($found as $row) {
					$rows[] = $row;
				}
			}
			$io->writeln(sprintf('Found %d pending attachment(s).', count($rows)));
			foreach ($rows as $row) {
				$io->writeln(sprintf('  - #%d (%s) %s', $row->getId(), get_class($row), $row->getSourceUrl() ?? ''));
			}
			return self::SUCCESS;
		}

		$stats = $this->uploadedFileService->retryPendingDownloads($limit);
		$io->success(sprintf('Pending %d → retried: %d ok, %d failed (left pending).',
			$stats['pending'], $stats['ok'], $stats['fail']));
		return self::SUCCESS;
	}
}
