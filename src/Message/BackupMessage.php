<?php

namespace Limas\Message;

/**
 * Scheduled trigger to run the full backup task. Handler invokes
 * BackupService::create() which packages DB dump + attachments +
 * secrets into a single .tar.gz under `limas.backup.directory` and
 * prunes older archives down to `limas.backup.retention_count`.
 */
final readonly class BackupMessage
{
}
