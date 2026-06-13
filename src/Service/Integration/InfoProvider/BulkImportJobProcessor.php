<?php

namespace Limas\Service\Integration\InfoProvider;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Entity\BulkImportJob;
use Limas\Entity\BulkImportJobItem;
use Limas\Entity\Part;
use Limas\Entity\PartCategory;
use Limas\Entity\StockEntry;
use Limas\Entity\StorageLocation;
use Limas\Service\Integration\InfoProvider\Dto\AggregatedPartCandidate;
use Limas\Service\Integration\InfoProvider\Enum\BulkImportDuplicatesBehavior;
use Limas\Service\Integration\InfoProvider\Enum\BulkImportItemStatus;
use Limas\Service\Integration\InfoProvider\Enum\BulkImportJobStatus;
use Limas\Service\ManufacturerCanonicalizer;


/**
 * Processes a BulkImportJob row by row. Called from both:
 *   - BulkImportRunCommand   (CLI fallback / batch ops)
 *   - BulkImportJobMessageHandler   (Messenger async transport — the default path)
 *
 * Per item:
 *   1. Aggregator MPN search.
 *   2. Manufacturer narrowing if the row carried a Manufacturer column.
 *   3. ExistingPartFinder match → status=skipped + link existing Part.
 *   4. Multiple remaining candidates → status=ambiguous.
 *   5. Single candidate → resolve Category/Storage (CSV override >
 *      job default, soft fallback when override doesn't resolve) →
 *      AggregatorImporter::import → success / warning.
 *
 * Final job status: any warning/ambiguous/failed → `partial`; clean → `completed`
 */
final readonly class BulkImportJobProcessor
{
	public function __construct(
		private EntityManagerInterface $em,
		private InfoProviderAggregator $aggregator,
		private AggregatorImporter     $importer
	)
	{
	}

	/**
	 * @param callable|null $onProgress Invoked after each item with (BulkImportJobItem $item, int $processedCount, int $totalCount). Used by the CLI wrapper to drive the progress bar.
	 *
	 * @return array{success: int, warning: int, skipped: int, ambiguous: int, failed: int}
	 */
	public function run(int $jobId, ?callable $onProgress = null): array
	{
		// Full-fan aggregator search across all enabled distributors easily
		// pushes through PHP's default 30s exec limit. Same reason the CLI
		// command lifts the cap.
		set_time_limit(0);
		ini_set('max_execution_time', '0');

		$job = $this->em->find(BulkImportJob::class, $jobId);
		if ($job === null) {
			throw new \RuntimeException(sprintf('BulkImportJob #%d not found', $jobId));
		}

		// Idempotency: re-running a finished job is a no-op so a botched Messenger retry can't double-import
		if ($job->getStatus() === BulkImportJobStatus::Completed || $job->getStatus() === BulkImportJobStatus::Partial) {
			return ['success' => 0, 'warning' => 0, 'skipped' => 0, 'ambiguous' => 0, 'failed' => 0];
		}

		$job->setStatus(BulkImportJobStatus::Running);
		$this->em->flush();

		$counts = ['success' => 0, 'warning' => 0, 'skipped' => 0, 'ambiguous' => 0, 'failed' => 0];
		$total = $job->getTotalRows();
		$processed = 0;

		foreach ($job->getItems() as $item) {
			if ($item->getStatus() !== BulkImportItemStatus::Pending) {
				$processed++;
				continue;
			}
			try {
				$this->processItem($job, $item);
			} catch (\Throwable $e) {
				$item->setStatus(BulkImportItemStatus::Failed)
					->setErrorMessage('Worker exception: ' . $e->getMessage());
			}
			$counts[$item->getStatus()->value] = ($counts[$item->getStatus()->value] ?? 0) + 1;
			$job->incrementProcessedRows();
			$this->em->flush();
			$processed++;
			if ($onProgress !== null) {
				$onProgress($item, $processed, $total);
			}
		}

		$hasIssue = $counts['warning'] > 0 || $counts['ambiguous'] > 0 || $counts['failed'] > 0;
		$job->setStatus($hasIssue ? BulkImportJobStatus::Partial : BulkImportJobStatus::Completed);
		$this->em->flush();

		return $counts;
	}

	private function processItem(BulkImportJob $job, BulkImportJobItem $item): void
	{
		$mpn = $item->getRawMpn();
		$rawMfr = $item->getRawManufacturer() ?? '';

		$candidates = $this->aggregator->searchByMpnAndMerge($mpn, null, 20);
		if ($candidates === []) {
			$item->setStatus(BulkImportItemStatus::Failed)
				->setErrorMessage('No aggregator candidates found for this MPN.');
			return;
		}

		if ($rawMfr !== '') {
			$mfrKey = ManufacturerCanonicalizer::normalize($rawMfr);
			$narrowed = [];
			foreach ($candidates as $c) {
				$candMfr = ManufacturerCanonicalizer::normalize($c->manufacturerName->chosenValue ?? '');
				if ($candMfr === $mfrKey) {
					$narrowed[] = $c;
				}
			}
			$candidates = $narrowed;
			if ($candidates === []) {
				$item->setStatus(BulkImportItemStatus::Failed)
					->setErrorMessage(sprintf('No candidate matches manufacturer "%s".', $rawMfr));
				return;
			}
		}

		if (count($candidates) > 1) {
			$alternatives = array_map(static fn(AggregatedPartCandidate $c) => sprintf(
				'%s / %s',
				$c->manufacturerName->chosenValue ?? '?',
				$c->manufacturerPartNumber->chosenValue ?? '?'
			), array_slice($candidates, 0, 5));
			$item->setStatus(BulkImportItemStatus::Ambiguous)
				->setErrorMessage('Multiple candidates match — add Manufacturer column to disambiguate. Alternatives: ' . implode(' | ', $alternatives));
			return;
		}

		$picked = $candidates[0];
		$mode = $job->getDuplicatesBehavior();
		$existing = $picked->existingPart !== null
			? $this->em->find(Part::class, $picked->existingPart->partId)
			: null;

		// UpdateStock branch — needs a parseable Quantity per row. Without
		// it we can't honour the restock semantic so we degrade to Skip with
		// a warning (NOT Failed: the row's other fields are still valid; the
		// operator can re-run with the column mapped).
		if ($mode === BulkImportDuplicatesBehavior::UpdateStock) {
			$qty = $this->parseQuantity($item->getRawQuantity());
			if ($qty === null) {
				if ($existing !== null) {
					$item->setStatus(BulkImportItemStatus::Skipped)
						->setExistingPart($existing)
						->setErrorMessage(sprintf(
							'Already in inventory as #%d; Quantity column missing or unparseable, no stock change.',
							$existing->getId()
						));
				} else {
					$item->setStatus(BulkImportItemStatus::Failed)
						->setErrorMessage('UpdateStock mode requires a parseable Quantity column; this row has none.');
				}
				return;
			}
			if ($existing !== null) {
				$this->addStockEntry($job, $existing, $qty, 'Bulk import: restock');
				$item->setExistingPart($existing)
					->setQuantityApplied($qty)
					->setStatus(BulkImportItemStatus::Success)
					->setErrorMessage(sprintf('Added %d to existing Part #%d.', $qty, $existing->getId()));
				return;
			}
			// No existing match → fall through to import + initial stock,
			// preserving the "create new + initial qty" branch of the restock semantic
		}

		// Skip branch — default protection against accidentally creating
		// duplicate Parts. UpdateStock with no existing match also reaches
		// this point (above branch fell through), but since `$existing` is
		// null here we just import normally.
		if ($mode === BulkImportDuplicatesBehavior::Skip && $existing !== null) {
			$item->setStatus(BulkImportItemStatus::Skipped)
				->setExistingPart($existing)
				->setErrorMessage(sprintf('Already in inventory as #%d.', $existing->getId()));
			return;
		}

		// CreateAnyway + Skip-no-match + UpdateStock-no-match all converge
		// here: import a fresh Part. CreateAnyway annotates with a warning
		// so the operator knows a duplicate was knowingly created.
		[$category, $catFallback] = $this->resolveCategory($item->getRawCategory(), $job->getDefaultCategory());
		[$storage, $stoFallback] = $this->resolveStorage($item->getRawStorage(), $job->getDefaultStorage());

		$part = $this->importer->import($picked, $category, $storage);
		$item->setPart($part);

		$warnings = [];
		if ($catFallback !== null) $warnings[] = $catFallback;
		if ($stoFallback !== null) $warnings[] = $stoFallback;

		if ($mode === BulkImportDuplicatesBehavior::CreateAnyway && $existing !== null) {
			$item->setExistingPart($existing);
			$warnings[] = sprintf(
				'Duplicate created on purpose — existing Part #%d kept untouched.',
				$existing->getId()
			);
		}

		// UpdateStock-no-match path: seed initial stock with the row's qty
		if ($mode === BulkImportDuplicatesBehavior::UpdateStock) {
			$qty = $this->parseQuantity($item->getRawQuantity());
			if ($qty !== null) {
				$this->addStockEntry($job, $part, $qty, 'Bulk import: initial stock');
				$item->setQuantityApplied($qty);
				$warnings[] = sprintf('Created new Part #%d with initial stock %d.', $part->getId(), $qty);
			}
		}

		if ($warnings !== []) {
			$item->setStatus(BulkImportItemStatus::Warning)
				->setErrorMessage(implode(' ', $warnings));
		} else {
			$item->setStatus(BulkImportItemStatus::Success);
		}
	}

	/**
	 * Lenient int parse: takes the raw cell, strips whitespace + non-digit
	 * suffixes ("12 pcs", "5 ks") and returns the positive int part. Returns
	 * null on blank / unparseable / non-positive — the caller branches on
	 * null to degrade gracefully.
	 */
	private function parseQuantity(?string $raw): ?int
	{
		if ($raw === null) {
			return null;
		}
		$trimmed = trim($raw);
		if ($trimmed === '') {
			return null;
		}
		if (preg_match('/^(\d+)/', $trimmed, $m) !== 1) {
			return null;
		}
		$n = (int)$m[1];
		return $n > 0 ? $n : null;
	}

	private function addStockEntry(BulkImportJob $job, Part $part, int $qty, string $comment): void
	{
		$entry = (new StockEntry)
			->setStockLevel($qty)
			->setComment($comment);
		$user = $job->getCreatedBy();
		if ($user !== null) {
			$entry->setUser($user);
		}
		$part->addStockLevel($entry);
	}

	/**
	 * @return array{0: PartCategory, 1: string|null}
	 */
	private function resolveCategory(?string $raw, PartCategory $default): array
	{
		if ($raw === null || trim($raw) === '') {
			return [$default, null];
		}
		$needle = trim($raw);
		$repo = $this->em->getRepository(PartCategory::class);
		$byPath = $repo->findOneBy(['categoryPath' => $needle]);
		if ($byPath !== null) {
			return [$byPath, null];
		}
		$matches = $repo->findBy(['name' => $needle]);
		if (count($matches) === 1) {
			return [$matches[0], null];
		}
		$warn = count($matches) > 1
			? sprintf('Category "%s" matches %d entries; using default.', $needle, count($matches))
			: sprintf('Category "%s" not found; used default "%s".', $needle, $default->getName());
		return [$default, $warn];
	}

	/**
	 * @return array{0: StorageLocation, 1: string|null}
	 */
	private function resolveStorage(?string $raw, StorageLocation $default): array
	{
		if ($raw === null || trim($raw) === '') {
			return [$default, null];
		}
		$needle = trim($raw);
		$repo = $this->em->getRepository(StorageLocation::class);
		$matches = $repo->findBy(['name' => $needle]);
		if (count($matches) === 1) {
			return [$matches[0], null];
		}
		$warn = count($matches) > 1
			? sprintf('Storage "%s" matches %d entries; using default.', $needle, count($matches))
			: sprintf('Storage "%s" not found; used default "%s".', $needle, $default->getName() ?? '?');
		return [$default, $warn];
	}
}
