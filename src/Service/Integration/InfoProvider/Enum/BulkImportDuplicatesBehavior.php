<?php

namespace Limas\Service\Integration\InfoProvider\Enum;


/**
 * What the bulk-import worker should do when a row's (mfr, MPN) matches a
 * Part that already exists in the local inventory
 *
 * Picked at job-submit time per upload — operator decides up-front based on
 * the use case the CSV represents:
 *   - parts list with no quantities → Skip (don't disturb inventory)
 *   - restock list with Quantity column → UpdateStock (add to existing,
 *     create-new+initial-stock when not found)
 *   - re-source from a different supplier where you intentionally want
 *     two Part records for the same MPN → CreateAnyway
 */
enum BulkImportDuplicatesBehavior: string
{
	// Existing Part matched → leave it alone, mark row Skipped with link.
	case Skip = 'skip';

	// Ignore the existing match → always run AggregatorImporter::import so
	// a brand new Part record is created. Operator can manually merge later
	// via the parts grid. Useful when the CSV represents a different
	// supplier/batch you intentionally want tracked separately.
	case CreateAnyway = 'create_anyway';

	// Restock semantics: existing match → add a StockEntry of the row's
	// Quantity to that Part. Missing match → create new Part + initial
	// StockEntry of Quantity. Requires the CSV to map a Quantity column;
	// rows without a parseable quantity fall back to Skip with a warning
	// so we don't silently treat blanks as 0 (no-op) or 1 (double-count
	// risk if the user forgot to map the column).
	case UpdateStock = 'update_stock';
}
