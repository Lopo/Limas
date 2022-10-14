<?php

namespace Limas\Controller\Actions\User;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Controller\Actions\ActionUtilTrait;
use Limas\Entity\User;
use Limas\Exceptions\UserLimitReachedException;
use Limas\Exceptions\UserProtectedException;
use Limas\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;


#[AsController]
class Put
	extends AbstractController
{
	use ActionUtilTrait;


	public function __construct(
		private readonly UserService                 $userService,
		private readonly EntityManagerInterface      $entityManager,
		private readonly SerializerInterface         $serializer,
		private readonly UserPasswordHasherInterface $userPasswordHasher
	)
	{
	}

	public function __invoke(Request $request, int $id): User
	{
		$data = $this->getItem($this->entityManager, User::class, $id);
		if ($data->isProtected()) {
			throw new UserProtectedException;
		}

		$data = $this->serializer->deserialize($request->getContent(), User::class, $request->attributes->get('_api_format') ?? $request->getRequestFormat(), [AbstractNormalizer::OBJECT_TO_POPULATE => $data]);
		if ($data->isActive() && $this->userService->checkUserLimit()) {
			throw new UserLimitReachedException;
		}
		$data->setPassword($this->userPasswordHasher->hashPassword($data, $data->getNewPassword()))
			->setNewPassword(null);
		$this->entityManager->flush();
		return $data;
	}
}
