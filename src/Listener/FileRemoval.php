<?php

namespace Limas\Listener;

use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Limas\Entity\UploadedFile;
use Limas\Service\ImageService;


class FileRemoval
	implements EventSubscriberInterface
{
	public function __construct(private readonly ImageService $imageService)
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
		foreach ($eventArgs->getObjectManager()->getUnitOfWork()->getScheduledEntityDeletions() as $entity) {
			if ($entity instanceof UploadedFile) {
				$this->imageService->delete($entity);
			}
		}
	}
}
