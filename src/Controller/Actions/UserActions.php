<?php

namespace Limas\Controller\Actions;

use ApiPlatform\Core\DataProvider\ItemDataProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Limas\Entity\User;
use Limas\Entity\UserPreference;
use Limas\Exceptions\OldPasswordWrongException;
use Limas\Exceptions\PasswordChangeNotAllowedException;
use Limas\Exceptions\UserLimitReachedException;
use Limas\Exceptions\UserProtectedException;
use Limas\Service\UserPreferenceService;
use Limas\Service\UserService;
use Nette\Utils\Json;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;


class UserActions
	extends AbstractController
{
	use ActionUtilTrait;


	public function __construct(
		private readonly UserService                 $userService,
		private readonly EntityManagerInterface      $entityManager,
		private readonly UserPreferenceService       $userPreferenceService,
		private readonly SerializerInterface         $serializer,
		private readonly UserPasswordHasherInterface $userPasswordHasher,
		private readonly ItemDataProviderInterface   $dataProvider
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

	public function PostAction(User $data): User
	{
		if ($this->userService->checkUserLimit() === true) {
			throw new UserLimitReachedException;
		}
		$data->setProvider($this->userService->getBuiltinProvider())
			->setPassword($this->userPasswordHasher->hashPassword($data, $data->getNewPassword()))
			->setNewPassword(null);
		$this->entityManager->flush();

//		$data->eraseCredentials();
		return $data;
	}

	public function GetProvidersAction()
	{
		//@todo
	}

	public function getAction(User $data): User
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

	public function PutUserAction(Request $request, int $id): User
	{
		$data = $this->getItem($this->dataProvider, User::class, $id);
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

	public function DeleteUserAction(Request $request, int $id): User
	{
		/** @var User $item */
		$item = $this->getItem($this->dataProvider, User::class, $id);
		if ($item->isProtected()) {
			throw new UserProtectedException;
		}
		$this->userPreferenceService->deletePreferences($item);
		$this->entityManager->remove($item);
		return $item;
	}

	public function changePasswordAction(Request $request, User $data, array $limas): User
	{
		if (!($limas['auth'] && $limas['auth']['allow_password_change'] ?? false)) {
			throw new PasswordChangeNotAllowedException;
		}

		$decoded = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
		if (empty($decoded['oldpassword']) || empty($decoded['newpassword'])) {
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
