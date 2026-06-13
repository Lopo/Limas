<?php

namespace Limas\Scheduler;

use Limas\Message\BackupMessage;
use Limas\Message\CreateStatisticSnapshotMessage;
use Limas\Message\RetryAttachmentDownloadsMessage;
use Limas\Message\SyncTipsMessage;
use Limas\Message\VersionCheckMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;


/**
 * Limas recurring jobs — driven by `messenger:consume scheduler_default async`
 *
 * Replaces the historic system-cron + `limas:cron:run` orchestrator:
 * one persistent messenger worker dispatches the recurring messages on
 * the cron-expression triggers below AND consumes them in-process, no
 * external cron entry required.
 *
 * Cron expressions are sourced from `config/limas.yaml` under
 * `limas.scheduler.*_cron` so operators can retime jobs per deployment
 * without touching code. Standard 5-field cron format, evaluated in the
 * server's local time zone. Add a new job by:
 *   1. write a Message + #[AsMessageHandler] pair under src/Message + src/MessageHandler
 *   2. add a `<name>_cron` key under `limas.scheduler` in limas.yaml
 *   3. add the `$cron` arg + ->add(...) line below
 *   4. wire the arg in config/services.yaml
 */
#[AsSchedule('default')]
final readonly class LimasSchedule
	implements ScheduleProviderInterface
{
	public function __construct(
		private string $versionCheckCron,
		private string $statisticSnapshotCron,
		private string $syncTipsCron,
		private string $backupCron,
		private string $retryAttachmentDownloadsCron
	)
	{
	}

	public function getSchedule(): Schedule
	{
		return (new Schedule)
			->add(RecurringMessage::cron($this->versionCheckCron, new VersionCheckMessage))
			->add(RecurringMessage::cron($this->statisticSnapshotCron, new CreateStatisticSnapshotMessage))
			->add(RecurringMessage::cron($this->syncTipsCron, new SyncTipsMessage))
			->add(RecurringMessage::cron($this->backupCron, new BackupMessage))
			->add(RecurringMessage::cron($this->retryAttachmentDownloadsCron, new RetryAttachmentDownloadsMessage));
	}
}
