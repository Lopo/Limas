<?php

namespace Limas\Listener;

use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Limas\Entity\UploadedFile;
use Limas\Service\UploadedFileService;


class FileRemoval
	implements EventSubscriberInterface
{
	public function __construct(private readonly UploadedFileService $uploadedFileService)
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
		$em = $eventArgs->getObjectManager();
		$uow = $em->getUnitOfWork();
		foreach ($uow->getScheduledEntityDeletions() as $entity) {
			if ($entity instanceof UploadedFile) {
				$this->uploadedFileService->delete($entity);
			}
		}
	}
}
