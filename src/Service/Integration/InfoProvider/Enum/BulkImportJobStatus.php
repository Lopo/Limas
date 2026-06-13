<?php

namespace Limas\Service\Integration\InfoProvider\Enum;


enum BulkImportJobStatus: string
{
	case Pending = 'pending'; // created, worker hasn't picked it up yet
	case Running = 'running'; // worker is mid-loop
	case Completed = 'completed'; // every item ended success / skipped
	case Partial = 'partial'; // worker finished, but some items failed / ambiguous / warning
	case Failed = 'failed'; // worker itself blew up before completing the job
}
