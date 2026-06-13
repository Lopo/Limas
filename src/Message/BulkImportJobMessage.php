<?php

namespace Limas\Message;

/**
 * Dispatched when a BulkImportJob is created via POST /api/bulk-import
 *
 * The actual processing lives in BulkImportJobProcessor; this message
 * is just a carrier so messenger:consume can pick up the job off the
 * `async` transport (Doctrine table by default).
 */
final readonly class BulkImportJobMessage
{
	public function __construct(public int $jobId)
	{
	}
}
