<?php

namespace Limas\Command;

use Limas\Service\CronLoggerService;
use Limas\Service\StatisticService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


#[AsCommand(
	name: 'limas:cron:create-statistic-snapshot',
	description: 'Creates a statistic snapshot',
)]
class CreateStatisticSnapshotCommand
	extends Command
{
	public function __construct(
		private readonly StatisticService  $statisticService,
		private readonly CronLoggerService $cronLoggerService
	)
	{
		parent::__construct();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$this->statisticService->createStatisticSnapshot();
		$this->cronLoggerService->markCronRun($this->getName());

		return Command::SUCCESS;
	}
}
