<?php

namespace Limas\Controller\Actions;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Entity\TipOfTheDay;
use Limas\Entity\TipOfTheDayHistory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;


class TipOfTheDayActions
	extends AbstractController
{
	public function __construct(private readonly EntityManagerInterface $entityManager)
	{
	}

	public function MarkAllTipsAsUnread(): Response
	{
		$user = $this->getUser();
		$qb = $this->entityManager->createQueryBuilder();
		$qb->delete(TipOfTheDayHistory::class, 'h')
			->andWhere($qb->expr()->eq('h.user', ':user'))
			->setParameter(':user', $user)
			->getQuery()->execute();
		return new Response('OK', Response::HTTP_OK, ['Content-Type' => 'text/html']);
	}

	public function MarkTipRead(TipOfTheDay $data): TipOfTheDay
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
