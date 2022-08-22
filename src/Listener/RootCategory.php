<?php

namespace Limas\Listener;

use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Limas\Entity\AbstractCategory;
use Limas\Exceptions\OnlySingleRootNodeAllowedException;
use Limas\Exceptions\RootMayNotBeDeletedException;


class RootCategory
	implements EventSubscriberInterface
{
	public function getSubscribedEvents(): array
	{
		return [
			Events::onFlush
		];
	}

	public function onFlush(OnFlushEventArgs $eventArgs): void
	{
		$em = $eventArgs->getObjectManager();
		$uow = $em->getUnitOfWork();
		foreach ($uow->getScheduledEntityInsertions() as $insertion) {
			if (is_a($insertion, AbstractCategory::class)) {
				$this->checkForRoot($insertion, $em);
			}
		}

		foreach ($uow->getScheduledEntityUpdates() as $updated) {
			if (is_a($updated, AbstractCategory::class)) {
				$this->checkForRoot($updated, $em);
			}
		}

		foreach ($uow->getScheduledEntityDeletions() as $deletion) {
			if (is_a($deletion, AbstractCategory::class)) {
				if ($deletion->getParent() === null) {
					throw new RootMayNotBeDeletedException;
				}
			}
		}
	}

	protected function checkForRoot(AbstractCategory $category, EntityManagerInterface $entityManager): void
	{
		if ($category->getParent() !== null) {
			return;
		}

		$roots = $entityManager->getRepository($category::class)->getRootNodes();
		if (count($roots) === 0) {
			return;
		}
		$rootNode = reset($roots);

		if ($rootNode->getId() !== $category->getId()) {
			throw new OnlySingleRootNodeAllowedException;
		}
	}
}
