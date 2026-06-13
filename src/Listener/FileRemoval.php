<?php

namespace Limas\Listener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Limas\Entity\Blob;
use Limas\Entity\UploadedFile;
use Limas\Service\ImageService;
use Psr\Log\LoggerInterface;


/**
 * Two-phase post-delete cleanup for UploadedFile rows
 *
 *  - onFlush: snapshot any cache-side state we need to clean up (thumbnail
 *    cache) AND remember which Blob each scheduled-for-delete attachment
 *    pointed at. We can't read those after the flush completes because
 *    the row + its blob proxy are gone.
 *
 *  - postFlush: walk every snapshotted Blob id, COUNT remaining references
 *    across every UploadedFile subclass table. If zero, the Blob is
 *    orphan — drop the row and unlink the on-disk file.
 *
 * Wired here (not in UploadedFileService::delete) so the prune happens
 * regardless of HOW the attachment was deleted: explicit service call,
 * controller em->remove, cascade orphan-removal from Part.attachments, …
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
class FileRemoval
{
	/** @var array<int, int> spl_object_id(uploadedFile) → blob_id */
	private array $blobIdsToCheck = [];


	public function __construct(
		private readonly ImageService       $imageService,
		private readonly FilesystemOperator $blobStorage,
		private readonly LoggerInterface    $logger
	)
	{
	}

	public function onFlush(OnFlushEventArgs $eventArgs): void
	{
		$em = $eventArgs->getObjectManager();
		foreach ($em->getUnitOfWork()->getScheduledEntityDeletions() as $entity) {
			if (!$entity instanceof UploadedFile) {
				continue;
			}
			// Cache cleanup ONLY — never call imageService->delete from a
			// listener (delete chains into UploadedFileService::delete →
			// em->flush, which re-fires this listener and infinite-loops).
			$this->imageService->dropThumbnailCache($entity);

			// Snapshot the Blob FK pre-delete so postFlush can refcount
			$blob = $entity->getBlob();
			if ($blob !== null && $blob->getId() !== null) {
				$this->blobIdsToCheck[spl_object_id($entity)] = $blob->getId();
			}
		}
	}

	public function postFlush(PostFlushEventArgs $eventArgs): void
	{
		if ($this->blobIdsToCheck === []) {
			return;
		}
		$em = $eventArgs->getObjectManager();
		$conn = $em->getConnection();
		$blobIds = array_values(array_unique($this->blobIdsToCheck));
		// Reset BEFORE the prune flush so the recursive postFlush event
		// fired by our own em->flush() doesn't re-enter with the same set
		$this->blobIdsToCheck = [];

		$subclassTables = [
			'PartAttachment',
			'ProjectAttachment',
			'FootprintAttachment',
			'FootprintImage',
			'ManufacturerICLogo',
			'StorageLocationImage',
			'TempUploadedFile',
			'TempImage'
		];

		$blobsToFlush = false;
		foreach ($blobIds as $blobId) {
			$stillReferenced = false;
			foreach ($subclassTables as $table) {
				$count = (int)$conn->fetchOne("SELECT COUNT(*) FROM `$table` WHERE blob_id = ?", [$blobId]);
				if ($count > 0) {
					$stillReferenced = true;
					break;
				}
			}
			if ($stillReferenced) {
				continue;
			}
			$blob = $em->find(Blob::class, $blobId);
			if ($blob === null) {
				continue;
			}
			$filename = $blob->getFilename();
			try {
				if ($this->blobStorage->fileExists($filename)) {
					$this->blobStorage->delete($filename);
				}
			} catch (FilesystemException $e) {
				$this->logger->info(sprintf('Blob #%d storage file %s prune failed: %s', $blobId, $filename, $e->getMessage()));
			}
			$em->remove($blob);
			$blobsToFlush = true;
		}
		if ($blobsToFlush) {
			$em->flush();
		}
	}
}
