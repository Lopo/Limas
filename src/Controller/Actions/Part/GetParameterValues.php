<?php

namespace Limas\Controller\Actions\Part;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Controller\Actions\ActionUtilTrait;
use Limas\Entity\PartParameter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;


#[AsController]
class GetParameterValues
	extends AbstractController
{
	use ActionUtilTrait;


	public function __construct(
		private readonly EntityManagerInterface $entityManager
	)
	{
	}

	public function __invoke(Request $request): JsonResponse
	{
		if (!$request->query->has('name')) {
			throw new \InvalidArgumentException("The parameter 'name' must be given");
		}
		if (!$request->query->has('valueType')) {
			throw new \InvalidArgumentException("The parameter 'valueType' must be given");
		}

		$qb = $this->entityManager->createQueryBuilder();
		if ($request->query->get('valueType') === 'string') {
			return $this->json(
				$qb->select('p.stringValue AS value')
					->from(PartParameter::class, 'p')
					->andWhere($qb->expr()->eq('p.name', ':name'))
					->andWhere($qb->expr()->eq('p.valueType', ':valueType'))
					->groupBy('p.stringValue')
					->setParameters([
						'name' => $request->query->get('name'),
						'valueType' => $request->query->get('valueType'),
					])
					->getQuery()->getArrayResult()
			);
		}
		return $this->json(
			$qb->select('p.value')
				->from(PartParameter::class, 'p')
				->andWhere($qb->expr()->eq('p.name', ':name'))
				->andWhere($qb->expr()->eq('p.valueType', ':valueType'))
				->groupBy('p.value')
				->setParameters([
					'name' => $request->query->get('name'),
					'valueType' => $request->query->get('valueType'),
				])
				->getQuery()->getArrayResult()
		);
	}
}
