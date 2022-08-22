<?php

namespace Limas\Listener;

use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Limas\Entity\AbstractCategory;


class CategoryPath
	implements EventSubscriberInterface
{
	public function __construct(private readonly array $limas)
	{
	}

	public function getSubscribedEvents(): array
	{
		return [
			Events::onFlush
		];
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
