<?php

namespace Limas\MessageHandler;

use Limas\Message\BulkImportJobMessage;
use Limas\Service\Integration\InfoProvider\BulkImportJobProcessor;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;


/**
 * Handles BulkImportJobMessage off the `async` transport
 *
 * Run a consumer with:  bin/console messenger:consume async
 *
 * Throws bubble up to Messenger so the retry strategy
 * (config/packages/messenger.yaml — 3 retries, exponential) applies.
 */
#[AsMessageHandler]
final readonly class BulkImportJobMessageHandler
{
	public function __construct(
		private BulkImportJobProcessor $processor
	)
	{
	}

	public function __invoke(BulkImportJobMessage $message): void
	{
		$this->processor->run($message->jobId);
	}
}
