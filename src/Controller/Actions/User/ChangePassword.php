<?php

namespace Limas\Controller\Actions\User;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Controller\Actions\ActionUtilTrait;
use Limas\Entity\User;
use Limas\Exceptions\OldPasswordWrongException;
use Limas\Exceptions\PasswordChangeNotAllowedException;
use Nette\Utils\Strings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;


#[AsController]
class ChangePassword
	extends AbstractController
{
	use ActionUtilTrait;


	public function __construct(
		private readonly EntityManagerInterface      $entityManager,
		private readonly UserPasswordHasherInterface $userPasswordHasher
	)
	{
	}

	public function __invoke(Request $request, User $data, array $limas): User
	{
		if (!($limas['auth'] && $limas['auth']['allow_password_change'] ?? false)) {
			throw new PasswordChangeNotAllowedException;
		}

		$decoded = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
		if (!isset($decoded['oldpassword']) || 0 === Strings::length($decoded['oldpassword'])
			|| !isset($decoded['newpassword']) || 0 === Strings::length($decoded['newpassword'])
		) {
			throw new \Exception('old password and new password need to be specified');
		}

		if (!$this->userPasswordHasher->isPasswordValid($data, $decoded['oldpassword'])) {
			throw new OldPasswordWrongException;
		}

		$data->setPassword($this->userPasswordHasher->hashPassword($data, $decoded['newpassword']));
		$this->entityManager->persist($data);
		$this->entityManager->flush();

		return $data;
	}
}
