<?php

namespace Limas\Service\Integration\InfoProvider\Enum;


enum BulkImportItemStatus: string
{
	case Pending = 'pending'; // not yet processed by the worker
	case Success = 'success'; // Part created cleanly
	case Warning = 'warning'; // Part created, but a CSV override didn't resolve and the job default was used
	case Skipped = 'skipped'; // existing Part matched in inventory (duplicatesBehavior=skip)
	case Ambiguous = 'ambiguous'; // multiple aggregator candidates match; operator must disambiguate
	case Failed = 'failed'; // aggregator found nothing OR import threw
}
