<?php

namespace Limas\Controller\Actions\Part;

use Limas\Controller\Actions\ActionUtilTrait;
use Limas\Entity\Part;
use Limas\Exceptions\InternalPartNumberNotUniqueException;
use Limas\Exceptions\PartLimitExceededException;
use Limas\Service\PartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Serializer\SerializerInterface;


#[AsController]
class Post
	extends AbstractController
{
	use ActionUtilTrait;


	public function __construct(
		private readonly PartService         $partService,
		private readonly SerializerInterface $serializer
	)
	{
	}

	public function __invoke(Request $request): Part
	{
		if ($this->partService->checkPartLimit()) {
			throw new PartLimitExceededException;
		}
		$part = $this->serializer->deserialize($request->getContent(), Part::class, 'jsonld');
		if (!$this->partService->isInternalPartNumberUnique((string)$part->getInternalPartNumber())) {
			throw new InternalPartNumberNotUniqueException;
		}
		return $part;
	}
}
