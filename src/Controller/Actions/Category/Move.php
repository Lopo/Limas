<?php

namespace Limas\Controller\Actions\Category;

use ApiPlatform\Api\IriConverterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Limas\Controller\Actions\ActionUtilTrait;
use Limas\Exceptions\MissingParentCategoryException;
use Limas\Exceptions\RootMayNotBeMovedException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;


#[AsController]
class Move
	extends AbstractController
{
	use ActionUtilTrait;


	public function __construct(
		private readonly EntityManagerInterface $entityManager
	)
	{
	}

	public function __invoke(Request $request, int $id, IriConverterInterface $iriConverter): Response
	{
		$entity = $this->getItem($this->entityManager, $this->getResourceClass($request), $id);
		$parentId = $request->request->get('parent');
		try {
			$parentEntity = $iriConverter->getResourceFromIri($parentId);
		} catch (\Exception $e) {
			throw new MissingParentCategoryException($parentId);
		}

		if ($entity->getLevel() === 0) {
			throw new RootMayNotBeMovedException;
		}

		$entity->setParent($parentEntity);
		$this->entityManager->flush();
		return new Response($parentId);
	}
}
