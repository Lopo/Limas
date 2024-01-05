<?php

namespace Limas\Controller\Actions;

use ApiPlatform\Doctrine\Orm\State\ItemProvider;
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
		private readonly ItemProvider           $dataProvider,
		private readonly EntityManagerInterface $entityManager
	)
	{
	}

	public function __invoke(Request $request, int $id): SystemNotice
	{
		$systemNotice = $this->getItem($this->dataProvider, SystemNotice::class, $id)
			->setAcknowledged();
		$this->entityManager->flush();
		return $systemNotice;
	}
}
