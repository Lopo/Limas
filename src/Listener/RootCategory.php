<?php

namespace Limas\Listener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Limas\Entity\AbstractCategory;
use Limas\Exceptions\OnlySingleRootNodeAllowedException;
use Limas\Exceptions\RootMayNotBeDeletedException;


#[AsDoctrineListener(event: Events::onFlush)]
class RootCategory
{
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
			if (is_a($deletion, AbstractCategory::class) && $deletion->getParent() === null) {
				throw new RootMayNotBeDeletedException;
			}
		}
	}

	protected function checkForRoot(AbstractCategory $category, EntityManagerInterface $entityManager): void
	{
		if ($category->getParent() !== null) {
			return;
		}

		$repo = $entityManager->getRepository($category::class);
		assert($repo instanceof \Gedmo\Tree\Entity\Repository\NestedTreeRepository);
		$roots = $repo->getRootNodes();
		if (count($roots) === 0) {
			return;
		}
		$rootNode = reset($roots);
		// `count($roots) > 0` guarantees reset() returns an entity, but
		// phpstan can't carry that narrowing across reset's array|false.
		assert($rootNode !== false);

		if ($rootNode->getId() !== $category->getId()) {
			throw new OnlySingleRootNodeAllowedException;
		}
	}
}
