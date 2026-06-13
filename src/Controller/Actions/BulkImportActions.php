<?php

namespace Limas\Controller\Actions;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Entity\BulkImportJob;
use Limas\Entity\BulkImportJobItem;
use Limas\Entity\PartCategory;
use Limas\Entity\StorageLocation;
use Limas\Entity\User;
use Limas\Message\BulkImportJobMessage;
use Limas\Service\Integration\InfoProvider\BulkImportCsvParser;
use Limas\Service\Integration\InfoProvider\Enum\BulkImportDuplicatesBehavior;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;


/**
 * REST endpoints for the Bulk Import feature
 *
 *   POST /api/bulk-import
 *     multipart/form-data: `file` (CSV) + `meta` JSON field with
 *     { hasHeader, mapping: {mpn, manufacturer?, category?, storage?},
 *       defaultCategoryId, defaultStorageLocationId, duplicatesBehavior? }
 *     Returns { jobId, totalRows }. Dispatches a BulkImportJobMessage
 *     onto the `async` Messenger transport — a `messenger:consume async`
 *     worker picks it up and runs BulkImportJobProcessor. FE polls
 *     /bulk-import-jobs/<id> for progress.
 *
 *   GET /api/bulk-import-jobs/{id}
 *     Returns job + per-item status. Used by FE progress panel polling.
 */
class BulkImportActions
	extends AbstractController
{
	public function __construct(
		private readonly EntityManagerInterface $em,
		private readonly BulkImportCsvParser    $csvParser,
		private readonly MessageBusInterface    $messageBus
	)
	{
	}

	#[Route(path: '/api/bulk-import', methods: ['POST'])]
	public function createJobAction(Request $request): JsonResponse
	{
		$file = $request->files->get('file');
		$metaJson = $request->request->get('meta', '');
		if ($file === null || !is_string($metaJson) || $metaJson === '') {
			return new JsonResponse(['error' => 'Multipart fields `file` and `meta` are required'], 400);
		}
		$meta = json_decode($metaJson, true);
		if (!is_array($meta)) {
			return new JsonResponse(['error' => '`meta` must be a JSON object'], 400);
		}

		$mapping = $meta['mapping'] ?? null;
		if (!is_array($mapping) || !isset($mapping['mpn']) || !is_int($mapping['mpn'])) {
			return new JsonResponse(['error' => '`mapping.mpn` (column index) is required'], 400);
		}

		$categoryId = (int)($meta['defaultCategoryId'] ?? 0);
		$storageId = (int)($meta['defaultStorageLocationId'] ?? 0);
		$category = $this->em->find(PartCategory::class, $categoryId);
		$storage = $this->em->find(StorageLocation::class, $storageId);
		if ($category === null || $storage === null) {
			return new JsonResponse(['error' => 'defaultCategoryId / defaultStorageLocationId must reference existing entities'], 400);
		}

		try {
			$rows = $this->csvParser->parse($file->getPathname());
		} catch (\Throwable $e) {
			return new JsonResponse(['error' => 'CSV parse failed: ' . $e->getMessage()], 400);
		}
		if ($rows === []) {
			return new JsonResponse(['error' => 'CSV is empty'], 400);
		}

		$hasHeader = ($meta['hasHeader'] ?? true) === true;
		if ($hasHeader) {
			array_shift($rows);
		}
		if ($rows === []) {
			return new JsonResponse(['error' => 'CSV has only a header row'], 400);
		}

		$mpnCol = $mapping['mpn'];
		$mfrCol = isset($mapping['manufacturer']) && is_int($mapping['manufacturer']) ? $mapping['manufacturer'] : null;
		$catCol = isset($mapping['category']) && is_int($mapping['category']) ? $mapping['category'] : null;
		$stoCol = isset($mapping['storage']) && is_int($mapping['storage']) ? $mapping['storage'] : null;
		// Quantity column is only meaningful in UpdateStock mode; we still
		// persist whatever the user mapped so a mid-job re-run with a
		// different duplicatesBehavior reads the same column.
		$qtyCol = isset($mapping['quantity']) && is_int($mapping['quantity']) ? $mapping['quantity'] : null;

		$job = new BulkImportJob;
		$job->setDefaultCategory($category);
		$job->setDefaultStorage($storage);
		$dupRaw = $meta['duplicatesBehavior'] ?? 'skip';
		$job->setDuplicatesBehavior(BulkImportDuplicatesBehavior::tryFrom(is_string($dupRaw) ? $dupRaw : 'skip') ?? BulkImportDuplicatesBehavior::Skip);
		$user = $this->getUser();
		if ($user instanceof User) {
			$job->setCreatedBy($user);
		}

		$total = 0;
		foreach ($rows as $i => $row) {
			$mpn = $this->cell($row, $mpnCol);
			if ($mpn === '') {
				// Skip blank rows silently — operators routinely leave
				// trailing empty lines in Excel exports. We do NOT
				// create a failed item for these; they're noise.
				continue;
			}
			$item = (new BulkImportJobItem)
				->setLine($i + 1)
				->setRawMpn($mpn)
				->setRawManufacturer($this->optionalCell($row, $mfrCol))
				->setRawCategory($this->optionalCell($row, $catCol))
				->setRawStorage($this->optionalCell($row, $stoCol))
				->setRawQuantity($this->optionalCell($row, $qtyCol));
			$job->addItem($item);
			$total++;
		}
		$job->setTotalRows($total);

		if ($total === 0) {
			return new JsonResponse(['error' => 'No usable rows (all MPN cells were empty)'], 400);
		}

		$this->em->persist($job);
		$this->em->flush();

		// Hand the job off to Messenger. Picked up by
		// `bin/console messenger:consume async` (a long-running worker
		// the operator/sysadmin starts once and supervises). If no
		// consumer is running the message just queues up safely.
		$this->messageBus->dispatch(new BulkImportJobMessage($job->getId()));

		return new JsonResponse([
			'jobId' => $job->getId(),
			'totalRows' => $total,
			'message' => 'Job queued — a messenger:consume worker will process it.'
		], 201);
	}

	#[Route(path: '/api/bulk-import-jobs/{id}', requirements: ['id' => '\d+'], methods: ['GET'])]
	public function getJobAction(int $id): JsonResponse
	{
		$job = $this->em->find(BulkImportJob::class, $id);
		if ($job === null) {
			return new JsonResponse(['error' => 'Job not found'], 404);
		}
		$items = [];
		foreach ($job->getItems() as $item) {
			$items[] = [
				'line' => $item->getLine(),
				'mpn' => $item->getRawMpn(),
				'manufacturer' => $item->getRawManufacturer(),
				'category' => $item->getRawCategory(),
				'storage' => $item->getRawStorage(),
				'rawQuantity' => $item->getRawQuantity(),
				'quantityApplied' => $item->getQuantityApplied(),
				'status' => $item->getStatus()->value,
				'errorMessage' => $item->getErrorMessage(),
				'partId' => $item->getPart()?->getId(),
				'partName' => $item->getPart()?->getName(),
				'existingPartId' => $item->getExistingPart()?->getId(),
				'existingPartName' => $item->getExistingPart()?->getName()
			];
		}
		return new JsonResponse([
			'id' => $job->getId(),
			'status' => $job->getStatus()->value,
			'totalRows' => $job->getTotalRows(),
			'processedRows' => $job->getProcessedRows(),
			'createdAt' => $job->getCreatedAt()->format(\DateTimeInterface::ATOM),
			'duplicatesBehavior' => $job->getDuplicatesBehavior()->value,
			'defaultCategory' => $job->getDefaultCategory()->getName(),
			'defaultStorage' => $job->getDefaultStorage()->getName(),
			'items' => $items
		]);
	}

	/**
	 * @param string[] $row
	 */
	private function cell(array $row, int $idx): string
	{
		return isset($row[$idx]) ? trim($row[$idx]) : '';
	}

	/**
	 * Trim + return null when the cell is blank or the column wasn't
	 * mapped. Used for optional fields where blank stays null in the DB.
	 *
	 * @param string[] $row
	 */
	private function optionalCell(array $row, ?int $idx): ?string
	{
		if ($idx === null) {
			return null;
		}
		$v = $this->cell($row, $idx);
		return $v === '' ? null : $v;
	}
}
