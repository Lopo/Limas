<?php

namespace Limas\Message;


/**
 * Scheduled trigger for pruning aged-out temp upload rows. Temp files are
 * created on upload, copied to a permanent attachment on save, then left
 * behind (no FK back to the part), so they accumulate. Handler delegates to
 * UploadedFileService::purgeExpiredTempUploads().
 */
final readonly class PurgeTempUploadsMessage
{
}
