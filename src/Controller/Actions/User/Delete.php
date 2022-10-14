<?php

namespace Limas\Controller\Actions\User;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Controller\Actions\ActionUtilTrait;
use Limas\Entity\User;
use Limas\Exceptions\UserProtectedException;
use Limas\Service\UserPreferenceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;


#[AsController]
class Delete
	extends AbstractController
{
	use ActionUtilTrait;


	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly UserPreferenceService  $userPreferenceService
	)
	{
	}

	public function __invoke(Request $request, int $id): User
	{
		/** @var User $item */
		$item = $this->getItem($this->entityManager, User::class, $id);
		if ($item->isProtected()) {
			throw new UserProtectedException;
		}
		$this->userPreferenceService->deletePreferences($item);
		$this->entityManager->remove($item);
		return $item;
	}
}
