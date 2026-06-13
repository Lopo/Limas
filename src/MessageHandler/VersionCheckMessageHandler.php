<?php

namespace Limas\MessageHandler;

use Limas\Message\VersionCheckMessage;
use Limas\Service\VersionService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;


#[AsMessageHandler]
final readonly class VersionCheckMessageHandler
{
	public function __construct(
		private VersionService $versionService
	)
	{
	}

	public function __invoke(VersionCheckMessage $message): void
	{
		$this->versionService->doVersionCheck();
	}
}
