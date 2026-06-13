<?php

namespace Limas\MessageHandler;

use Limas\Message\SyncTipsMessage;
use Limas\Service\TipOfTheDayService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;


#[AsMessageHandler]
final readonly class SyncTipsMessageHandler
{
	public function __construct(
		private TipOfTheDayService $tipOfTheDayService
	)
	{
	}

	public function __invoke(SyncTipsMessage $message): void
	{
		$this->tipOfTheDayService->syncTips();
	}
}
