<?php

namespace Limas\Controller\Actions\Part;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Controller\Actions\ActionUtilTrait;
use Limas\Entity\PartParameter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;


#[AsController]
class GetParameterNames
	extends AbstractController
{
	use ActionUtilTrait;


	public function __construct(
		private readonly EntityManagerInterface $entityManager
	)
	{
	}

	public function __invoke(): JsonResponse
	{
		return $this->json(
			$this->entityManager->createQueryBuilder()
				->select('p.name, p.description, p.valueType, u.name AS unitName')
				->from(PartParameter::class, 'p')
				->leftJoin('p.unit', 'u')
				->groupBy('p.name, p.description, p.valueType, u.name, u.symbol')
				->getQuery()->getArrayResult()
		);
	}
}
