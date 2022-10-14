<?php

namespace Limas\Controller\Actions\Part;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Controller\Actions\ActionUtilTrait;
use Limas\Entity\Part;
use Limas\Entity\StockEntry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;


#[AsController]
class AddStock
	extends AbstractController
{
	use ActionUtilTrait;


	public function __construct(
		private readonly EntityManagerInterface $entityManager
	)
	{
	}

	public function __invoke(Request $request, int $id): Part
	{
		$part = $this->entityManager->find(Part::class, $id);
		$stock = (new StockEntry)
			->setUser($this->getUser())
			->setStockLevel($request->request->getInt('quantity'));
		if ($request->request->get('price') !== null) {
			$stock->setPrice((float)$request->request->get('price'));
		}
		if ($request->request->has('comment') && $request->request->get('comment') !== null) {
			$stock->setComment($request->request->get('comment'));
		}

		$part->addStockLevel($stock);
		$this->entityManager->persist($stock);
		$this->entityManager->flush();

		return $part;
	}
}
