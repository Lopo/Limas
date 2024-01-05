<?php

namespace Limas\Controller\Actions;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\Exception\ItemNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Limas\Exceptions\MissingParentCategoryException;
use Limas\Exceptions\RootMayNotBeMovedException;
use Limas\Exceptions\RootNodeNotFoundException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;


class CategoryActions
	extends AbstractController
{
	use ActionUtilTrait;


	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly ItemProvider           $dataProvider
	)
	{
	}

	public function GetRootNodeAction(Request $request, SerializerInterface $serializer): Response
	{
		$roots = $this->entityManager->getRepository($this->getResourceClass($request))->getRootNodes();
		if (0 === count($roots)) {
			throw new RootNodeNotFoundException;
		}
		return new Response($serializer->serialize(reset($roots), $request->getRequestFormat(), ['groups' => ['default', 'tree']]));
	}

	/**
	 * @throws MissingParentCategoryException
	 * @throws RootMayNotBeMovedException
	 */
	public function MoveAction(Request $request, int $id, IriConverterInterface $iriConverter): Response
	{
		$entity = $this->getItem($this->dataProvider, $this->getResourceClass($request), $id);
		$parentId = $request->request->get('parent');
		try {
			$parentEntity = $iriConverter->getResourceFromIri($parentId);
		} catch (\InvalidArgumentException|ItemNotFoundException $e) {
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
