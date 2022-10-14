<?php

namespace Limas\Controller\Actions\TipOfTheDay;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Entity\TipOfTheDay;
use Limas\Entity\TipOfTheDayHistory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Attribute\AsController;


#[AsController]
class MarkTipRead
	extends AbstractController
{
	public function __construct(private readonly EntityManagerInterface $entityManager)
	{
	}

	public function __invoke(TipOfTheDay $data): TipOfTheDay
	{
		$user = $this->getUser();
		if (null === $this->entityManager->getRepository(TipOfTheDayHistory::class)->findOneBy(['user' => $user, 'name' => $data->getName()])) {
			$this->entityManager->persist((new TipOfTheDayHistory)
				->setUser($user)
				->setName($data->getName()));
			$this->entityManager->flush();
		}
		return $data;
	}
}
