<?php

namespace Limas\Listener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Limas\Entity\UploadedFile;
use Limas\Service\ImageService;


#[AsDoctrineListener(event: Events::onFlush)]
readonly class FileRemoval
{
	public function __construct(private ImageService $imageService)
	{
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
