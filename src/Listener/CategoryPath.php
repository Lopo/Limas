<?php

namespace Limas\Listener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Limas\Entity\AbstractCategory;


#[AsDoctrineListener(event: Events::onFlush)]
readonly class CategoryPath
{
	public function __construct(private array $limas)
	{
	}

	public function onFlush(OnFlushEventArgs $eventArgs): void
	{
		$uow = $eventArgs->getObjectManager()->getUnitOfWork();

		foreach ($uow->getScheduledEntityInsertions() as $entity) {
			if ($entity instanceof AbstractCategory) {
				$this->updateCategoryPaths($entity, $eventArgs);
			}
		}

		foreach ($uow->getScheduledEntityUpdates() as $entity) {
			if ($entity instanceof AbstractCategory) {
				$this->updateCategoryPaths($entity, $eventArgs);
			}
		}
	}

	private function updateCategoryPaths(AbstractCategory $category, OnFlushEventArgs $eventArgs): void
	{
		$entityManager = $eventArgs->getObjectManager();

		$category->setCategoryPath($category->generateCategoryPath($this->limas['category']['path_separator']));

		$entityManager->getUnitOfWork()->recomputeSingleEntityChangeSet(
			$entityManager->getClassMetadata(get_class($category)),
			$category
		);

		foreach ($category->getChildren() as $child) {
			$this->updateCategoryPaths($child, $eventArgs);
		}
	}
}
