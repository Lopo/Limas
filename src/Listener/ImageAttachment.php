<?php

namespace Limas\Listener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Limas\Entity\PartAttachment;
use Limas\Service\ImageService;


#[AsDoctrineListener(event: Events::postLoad)]
readonly class ImageAttachment
{
	public function __construct(private ImageService $imageService)
	{
	}

	public function postLoad(LifecycleEventArgs $args): void
	{
		if ($args->getObject() instanceof PartAttachment) {
			$entity = $args->getObject();
			$entity->setIsImage($this->imageService->canHandleMimetype($entity->getMimetype()));
		}
	}
}
