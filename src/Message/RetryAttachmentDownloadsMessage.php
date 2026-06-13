<?php

namespace Limas\Message;

/**
 * Scheduled trigger for the URL-only-attachment retry pass. Manufacturer
 * datasheets routinely 502 / Cloudflare-block at import time; a daily
 * second attempt often succeeds without user help. Handler delegates to
 * UploadedFileService::retryPendingDownloads().
 */
final readonly class RetryAttachmentDownloadsMessage
{
}
