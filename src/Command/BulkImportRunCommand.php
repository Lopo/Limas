<?php

namespace Limas\Command;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Entity\BulkImportJob;
use Limas\Service\Integration\InfoProvider\BulkImportJobProcessor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;


/**
 * CLI fallback for processing a BulkImportJob
 *
 * The default path is now Messenger — POST /api/bulk-import dispatches
 * a BulkImportJobMessage and `bin/console messenger:consume async`
 * picks it up. This command stays available for batch ops / debugging
 * where you want to process a specific jobId synchronously with a
 * progress bar.
 */
#[AsCommand(
	name: 'limas:bulk-import:run',
	description: 'Synchronously process a BulkImportJob (CLI fallback — the async path is messenger:consume).'
)]
final class BulkImportRunCommand
	extends Command
{
	public function __construct(
		private readonly EntityManagerInterface $em,
		private readonly BulkImportJobProcessor $processor
	)
	{
		parent::__construct();
	}

	protected function configure(): void
	{
		$this->addArgument('jobId', InputArgument::REQUIRED, 'ID of the BulkImportJob row to process.');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);
		$jobId = (int)$input->getArgument('jobId');
		$job = $this->em->find(BulkImportJob::class, $jobId);
		if ($job === null) {
			$io->error(sprintf('BulkImportJob #%d not found', $jobId));
			return self::FAILURE;
		}

		$io->section(sprintf('Bulk import job #%d (%d rows)', $jobId, $job->getTotalRows()));
		$bar = $io->createProgressBar($job->getTotalRows());
		$bar->start();

		$counts = $this->processor->run(
			$jobId,
			fn() => $bar->advance(),
		);

		$bar->finish();
		$io->newLine(2);

		$io->table(
			['status', 'count'],
			[
				['success', $counts['success']],
				['warning', $counts['warning']],
				['skipped', $counts['skipped']],
				['ambiguous', $counts['ambiguous']],
				['failed', $counts['failed']]
			]
		);

		// Re-fetch the final status from the processor's transaction.
		$this->em->refresh($job);
		$io->success(sprintf('Job #%d → %s', $jobId, $job->getStatus()->value));
		return self::SUCCESS;
	}
}
