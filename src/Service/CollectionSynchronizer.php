<?php

namespace Limas\Service;

use Doctrine\ORM\EntityManagerInterface;


/**
 * Generic service for synchronizing nested collections in entities.
 * Workaround for API Platform 4.x bug with nested collections on PUT operations.
 * @see https://github.com/api-platform/api-platform/issues/2855
 */
final readonly class CollectionSynchronizer
{
	public function __construct(
		private EntityManagerInterface $em
	)
	{
	}

	/**
	 * Synchronize a OneToMany collection
	 *
	 * @template T of object
	 * @param iterable<T> $existingItems Current items in the collection
	 * @param iterable<T> $newItems New items from request
	 * @param callable(T): ?int $getIdCallback Get ID from item (return null for new items)
	 * @param callable(T): ?int $getRelationIdCallback Get related entity ID (e.g., Part ID from ProjectPart)
	 * @param callable(T, T): void $updateCallback Update existing item with new data
	 * @param callable(int): ?object $findRelatedCallback Find related entity by ID (for new items)
	 * @param callable(object): T $createCallback Create new item from related entity
	 * @param callable(T): void $removeCallback Remove item from parent collection
	 */
	public function syncOneToMany(
		iterable $existingItems,
		iterable $newItems,
		callable $getIdCallback,
		callable $getRelationIdCallback,
		callable $updateCallback,
		callable $findRelatedCallback,
		callable $createCallback,
		callable $removeCallback
	): void
	{
		$existingById = [];
		foreach ($existingItems as $item) {
			$relationId = $getRelationIdCallback($item);
			if ($relationId !== null) {
				$existingById[$relationId] = $item;
			}
		}

		$newRelationIds = [];
		foreach ($newItems as $newItem) {
			$relationId = $getRelationIdCallback($newItem);
			if ($relationId === null) {
				continue;
			}
			$newRelationIds[] = $relationId;

			if (isset($existingById[$relationId])) {
				// Update existing
				$updateCallback($existingById[$relationId], $newItem);
			} else {
				// Create new
				$relatedEntity = $findRelatedCallback($relationId);
				if ($relatedEntity === null) {
					continue;
				}
				$newEntity = $createCallback($relatedEntity);
				$updateCallback($newEntity, $newItem);
				$this->em->persist($newEntity);
			}
		}

		// Remove items not in new list
		foreach ($existingItems as $item) {
			$relationId = $getRelationIdCallback($item);
			if ($relationId !== null && !in_array($relationId, $newRelationIds, true)) {
				$removeCallback($item);
				$this->em->remove($item);
			}
		}
	}

	/**
	 * Synchronize attachments collection (simpler case - items identified by their own ID)
	 *
	 * @template T of object
	 * @param iterable<T> $existingItems Current items
	 * @param iterable<T> $newItems New items from request
	 * @param callable(T): ?int $getIdCallback Get item ID
	 * @param callable(T): void $setupNewCallback Setup new item (set parent reference etc.)
	 * @param callable(T): void $removeCallback Remove item from parent
	 */
	public function syncAttachments(
		iterable $existingItems,
		iterable $newItems,
		callable $getIdCallback,
		callable $setupNewCallback,
		callable $removeCallback
	): void
	{
		$newIds = [];
		foreach ($newItems as $newItem) {
			$id = $getIdCallback($newItem);
			if ($id !== null) {
				$newIds[] = $id;
			} else {
				// New attachment
				$setupNewCallback($newItem);
				$this->em->persist($newItem);
			}
		}

		// Remove items not in new list
		foreach ($existingItems as $existing) {
			$id = $getIdCallback($existing);
			if ($id !== null && !in_array($id, $newIds, true)) {
				$removeCallback($existing);
				$this->em->remove($existing);
			}
		}
	}
}