<?php

namespace Limas\Controller\Actions;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Service\UserPreferenceService;
use Limas\Service\UserService;
use Nette\Utils\Json;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;


#[AsController]
class UserActions
	extends AbstractController
{
	use ActionUtilTrait;


	public function __construct(
		private readonly UserService                 $userService,
		private readonly EntityManagerInterface      $entityManager,
		private readonly UserPreferenceService       $userPreferenceService,
		private readonly SerializerInterface         $serializer,
		private readonly UserPasswordHasherInterface $userPasswordHasher
	)
	{
	}

	#[Route(path: '/api/users/login')]
	public function LoginAction(): JsonResponse
	{
		$user = $this->userService->getCurrentUser();
		$userPreferences = $this->userPreferenceService->getPreferences($user);
		$arrayUserPreferences = [];

		foreach ($userPreferences as $userPreference) {
			$arrayUserPreferences[] = [
				'preferenceKey' => $userPreference->getPreferenceKey(),
				'preferenceValue' => $userPreference->getPreferenceValue(),
			];
		}

		$user->setInitialUserPreferences(Json::encode($arrayUserPreferences))
			->eraseCredentials();

		return new JsonResponse($this->serializer->serialize($user, 'jsonld'), Response::HTTP_OK, ['Content-Type' => 'application/ld+json'], true);
	}

	#[Route(path: '/api/users/logout')]
	public function logoutAction()
	{
		// @todo
	}

//	public function GetProvidersAction()
//	{
//		//@todo
//	}
}
