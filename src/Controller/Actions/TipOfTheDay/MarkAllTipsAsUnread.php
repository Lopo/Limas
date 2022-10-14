<?php

namespace Limas\Controller\Actions\TipOfTheDay;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Entity\TipOfTheDayHistory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;


#[AsController]
class MarkAllTipsAsUnread
	extends AbstractController
{
	public function __construct(private readonly EntityManagerInterface $entityManager)
	{
	}

	public function __invoke(): Response
	{
		$user = $this->getUser();
		$qb = $this->entityManager->createQueryBuilder();
		$qb->delete(TipOfTheDayHistory::class, 'h')
			->andWhere($qb->expr()->eq('h.user', ':user'))
			->setParameter(':user', $user)
			->getQuery()->execute();
		return new Response('OK', Response::HTTP_OK, ['Content-Type' => 'text/html']);
	}
}
