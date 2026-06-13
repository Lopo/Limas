<?php

namespace Limas\Service\Integration\InfoProvider;


/**
 * Minimal CSV parser tailored to the Bulk Import flow
 *
 * We don't pull in League\Csv or similar — fgetcsv() from PHP core is
 * fine for our shape (BOM, MPN, manufacturer, category, storage). The
 * one piece of cleverness here is delimiter auto-detection: a sample
 * of the first line is scanned for the highest-count of `,` / `;` /
 * `\t` and that wins. Stops short of full RFC-4180 corner cases but
 * covers the formats real users paste (Excel "Save As CSV", LibreOffice
 * "CSV with semicolons", tab-separated KiCad BOMs).
 */
final class BulkImportCsvParser
{
	/**
	 * Parse the uploaded CSV file. Returns a list of rows; each row is
	 * a flat array of string cells in source order. Header detection
	 * is the CALLER's responsibility — pass the first row through your
	 * mapping logic separately.
	 *
	 * @return string[][]
	 */
	public function parse(string $filePath): array
	{
		$handle = @fopen($filePath, 'r');
		if ($handle === false) {
			throw new \RuntimeException(sprintf('Could not open CSV file: %s', $filePath));
		}
		try {
			// Sniff the delimiter from the first ~1 KB so we don't
			// fight tab- vs. semicolon-separated dumps
			$sample = (string)fread($handle, 1024);
			rewind($handle);
			$delimiter = $this->detectDelimiter($sample);

			// Skip a UTF-8 BOM if present — Excel "Save as CSV (UTF-8)"
			// loves to plant one, fgetcsv doesn't strip
			if (str_starts_with($sample, "\xEF\xBB\xBF")) {
				fseek($handle, 3);
			}

			$rows = [];
			while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
				// fgetcsv returns [null] for blank lines; skip them
				if ($row === [null]) {
					continue;
				}
				$rows[] = array_map(static fn($c) => is_string($c) ? trim($c) : '', $row);
			}
			return $rows;
		} finally {
			fclose($handle);
		}
	}

	/**
	 * @return non-empty-string
	 */
	private function detectDelimiter(string $sample): string
	{
		$candidates = [",", ";", "\t", "|"];
		$best = ',';
		$bestCount = -1;
		foreach ($candidates as $d) {
			$c = substr_count($sample, $d);
			if ($c > $bestCount) {
				$bestCount = $c;
				$best = $d;
			}
		}
		return $best;
	}
}
