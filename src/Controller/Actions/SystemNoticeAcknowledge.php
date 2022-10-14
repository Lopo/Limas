<?php

namespace Limas\Controller\Actions;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Entity\SystemNotice;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;


#[AsController]
class SystemNoticeAcknowledge
	extends AbstractController
{
	use ActionUtilTrait;


	public function __construct(
		private readonly EntityManagerInterface $entityManager
	)
	{
	}

	public function __invoke(Request $request, int $id): SystemNotice
	{
		$systemNotice = $this->getItem($this->entityManager, SystemNotice::class, $id)
			->setAcknowledged();
		$this->entityManager->flush();
		return $systemNotice;
	}
}
