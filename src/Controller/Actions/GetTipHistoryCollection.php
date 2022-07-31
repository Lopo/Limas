<?php

namespace Limas\Controller\Actions;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Entity\TipOfTheDayHistory;
use Limas\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;


#[AsController]
class GetTipHistoryCollection
	extends AbstractController
{
	public function __invoke(Request $request, EntityManagerInterface $entityManager, UserService $userService): array
	{
		$user = $this->getUser();
		$resultCollection = [];
		foreach ($entityManager->getRepository(TipOfTheDayHistory::class)->findAll() as $item) {
			if ($item->getUser() == $user) {
				$resultCollection[] = $item;
			}
		}
		return $resultCollection;
	}
}
