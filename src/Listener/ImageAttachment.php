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
			// URL-only attachments have no Blob yet → no mimetype to
			// classify. Treat as non-image; once the retry CLI attaches
			// a Blob the flag gets reclassified on the next load.
			$mt = $entity->getMimetype();
			$entity->setIsImage($mt !== null ? $this->imageService->canHandleMimetype($mt) : false);
		}
	}
}
