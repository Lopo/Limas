<?php

namespace Limas\Listener;

use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Limas\Entity\PartAttachment;
use Limas\Service\ImageService;


class ImageAttachment
	implements EventSubscriberInterface
{
	public function __construct(private readonly ImageService $imageService)
	{
	}

	public function getSubscribedEvents(): array
	{
		return [
			Events::postLoad
		];
	}

	public function postLoad(LifecycleEventArgs $args): void
	{
		if ($args->getEntity() instanceof PartAttachment) {
			$entity = $args->getEntity();
			$entity->setIsImage($this->imageService->canHandleMimetype($entity->getMimeType()));
		}
	}
}
