<?php

namespace Limas\Command;

use Limas\Service\CronLoggerService;
use Limas\Service\VersionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


#[AsCommand(
	name: 'limas:cron:versioncheck',
	description: 'Checks for Limas updates',
)]
class CheckForUpdatesCommand
	extends Command
{
	public function __construct(
		private readonly VersionService    $versionService,
		private readonly CronLoggerService $cronLoggerService
	)
	{
		parent::__construct();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$this->versionService->doVersionCheck();
		$this->cronLoggerService->markCronRun('limas:cron:versioncheck');

		return Command::SUCCESS;
	}
}
