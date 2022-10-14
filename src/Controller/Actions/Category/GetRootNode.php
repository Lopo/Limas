<?php

namespace Limas\Controller\Actions\Category;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Controller\Actions\ActionUtilTrait;
use Limas\Entity\AbstractCategory;
use Limas\Exceptions\RootNodeNotFoundException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;


#[AsController]
class GetRootNode
	extends AbstractController
{
	use ActionUtilTrait;


	public function __construct(
		private readonly EntityManagerInterface $entityManager
	)
	{
	}

	public function __invoke(Request $request): AbstractCategory
	{
		$roots = $this->entityManager->getRepository($this->getResourceClass($request))->getRootNodes();
		if (0 === count($roots)) {
			throw new RootNodeNotFoundException;
		}
		return reset($roots);
	}
}
