<?php

namespace Limas\Controller\Actions;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\DataProvider\ItemDataProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Limas\Entity\AbstractCategory;
use Limas\Exceptions\MissingParentCategoryException;
use Limas\Exceptions\RootMayNotBeMovedException;
use Limas\Exceptions\RootNodeNotFoundException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class CategoryActions
	extends AbstractController
{
	use ActionUtilTrait;


	public function __construct(
		private readonly EntityManagerInterface    $entityManager,
		private readonly ItemDataProviderInterface $dataProvider
	)
	{
	}

	public function GetRootNodeAction(Request $request): AbstractCategory
	{
		$roots = $this->entityManager->getRepository($this->getResourceClass($request))->getRootNodes();
		if (!count($roots)) {
			throw new RootNodeNotFoundException;
		}
		return reset($roots);
	}

	public function MoveAction(Request $request, int $id, IriConverterInterface $iriConverter): Response
	{
		$entity = $this->getItem($this->dataProvider, $this->getResourceClass($request), $id);
		$parentId = $request->request->get('parent');
		$parentEntity = $iriConverter->getItemFromIri($parentId);

		if ($parentEntity === null) {
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
