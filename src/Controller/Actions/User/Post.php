<?php

namespace Limas\Controller\Actions\User;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Controller\Actions\ActionUtilTrait;
use Limas\Entity\User;
use Limas\Exceptions\UserLimitReachedException;
use Limas\Service\UserPreferenceService;
use Limas\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\SerializerInterface;


#[AsController]
class Post
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

	public function __invoke(User $data): User
	{
		if ($this->userService->checkUserLimit() === true) {
			throw new UserLimitReachedException;
		}
		$data->setProvider($this->userService->getBuiltinProvider())
			->setPassword($this->userPasswordHasher->hashPassword($data, $data->getNewPassword()))
			->setNewPassword(null);
		$this->entityManager->flush();

		$data->eraseCredentials();
		return $data;
	}
}
