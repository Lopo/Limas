<?php

namespace Limas\Command\Cron;

use Limas\Service\CronLoggerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


#[AsCommand(
	name: 'limas:cron:clear',
	description: 'Clears all cron logger entries',
)]
class ClearCommand
	extends Command
{
	public function __construct(private readonly CronLoggerService $cronLoggerService)
	{
		parent::__construct();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$this->cronLoggerService->clear();

		return Command::SUCCESS;
	}
}
