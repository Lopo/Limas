<?php

namespace Limas\Controller\Actions\Part;

use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\ORM\EntityManagerInterface;
use Limas\Controller\Actions\ActionUtilTrait;
use Limas\Entity\Part;
use Limas\Service\PartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Attribute\AsController;


#[AsController]
class GetParts
	extends AbstractController
{
	use ActionUtilTrait;


	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly PartService            $partService,
		private readonly CollectionProvider     $provider
	)
	{
	}

	public function __invoke(): iterable
	{
		$items = $this->provider->provide((new GetCollection)->withClass(Part::class))->getIterator();
		foreach ($items as $part) {
			if ($part->isMetaPart()) {
				$sum = 0;
				foreach ($this->partService->getMatchingMetaParts($part) as $matchingPart) {
					$sum += $matchingPart->getStockLevel();
				}
				$part->setStockLevel($sum);
			}
		}
		return $items;
	}
}
