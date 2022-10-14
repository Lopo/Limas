<?php

namespace Limas\Controller\Actions\User;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Controller\Actions\ActionUtilTrait;
use Limas\Entity\User;
use Limas\Entity\UserPreference;
use Nette\Utils\Json;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Attribute\AsController;


#[AsController]
class Get
	extends AbstractController
{
	use ActionUtilTrait;


	public function __construct(
		private readonly EntityManagerInterface $entityManager
	)
	{
	}

	public function __invoke(User $data): User
	{
		$user = $this->getUser();
		if ($user->getId() === $data->getId()) {
			$userPreferences = $this->entityManager->getRepository(UserPreference::class)->getPreferences($user);
			$arrayUserPreferences = [];
			foreach ($userPreferences as $userPreference) {
				$arrayUserPreferences[] = [
					'preferenceKey' => $userPreference->getPreferenceKey(),
					'preferenceValue' => $userPreference->getPreferenceValue()
				];
			}
			$data->setInitialUserPreferences(Json::encode($arrayUserPreferences));
		}
		return $data;
	}
}
