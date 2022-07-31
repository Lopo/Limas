<?php

namespace Limas\Command;

use Limas\Service\CronLoggerService;
use Limas\Service\TipOfTheDayService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


#[AsCommand(
	name: 'limas:cron:synctips',
	description: 'Syncronizes the tips from the PartKeepr website',
)]
class SyncTipsCommand
	extends Command
{
	public function __construct(
		private readonly TipOfTheDayService $tipOfTheDayService,
		private readonly CronLoggerService  $cronLoggerService
	)
	{
		parent::__construct();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$this->tipOfTheDayService->syncTips();
		$this->cronLoggerService->markCronRun('limas:cron:synctips');

		return Command::SUCCESS;
	}
}
