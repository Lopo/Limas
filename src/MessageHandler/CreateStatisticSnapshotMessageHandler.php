<?php

namespace Limas\MessageHandler;

use Limas\Message\CreateStatisticSnapshotMessage;
use Limas\Service\StatisticService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;


#[AsMessageHandler]
final readonly class CreateStatisticSnapshotMessageHandler
{
	public function __construct(
		private StatisticService $statisticService
	)
	{
	}

	public function __invoke(CreateStatisticSnapshotMessage $message): void
	{
		$this->statisticService->createStatisticSnapshot();
	}
}
