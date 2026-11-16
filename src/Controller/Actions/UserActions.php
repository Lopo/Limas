<?php

namespace Limas\Controller\Actions;

use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NoResultException;
use Limas\Entity\RefreshToken;
use Limas\Entity\User;
use Limas\Entity\UserPreference;
use Limas\Exceptions\OldPasswordWrongException;
use Limas\Exceptions\PasswordChangeNotAllowedException;
use Limas\Exceptions\UserLimitReachedException;
use Limas\Exceptions\UserProtectedException;
use Limas\Service\UserPreferenceService;
use Limas\Service\UserService;
use Nette\Utils\Json;
use Nette\Utils\Strings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
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
		private readonly ItemProvider                $dataProvider
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

		$user->setInitialUserPreferences(Json::encode($arrayUserPreferences));

		// Same groups as GET /api/users/{id}: only the user's public fields
		return new JsonResponse($this->serializer->serialize($user, 'jsonld', ['groups' => ['default', 'user:detail']]), Response::HTTP_OK, ['Content-Type' => 'application/ld+json'], true);
	}

	#[Route(path: '/api/users/logout')]
	public function logoutAction(): JsonResponse
	{
		// Kill the server-side refresh token so a copied token can't be used to
		// mint fresh access tokens after logout; the JWT itself dies on its own
		// short TTL. Then drop the auth cookies client-side.
		$this->revokeRefreshTokens($this->userService->getCurrentUser()->getUserIdentifier());

		$response = new JsonResponse(['success' => true]);
		$response->headers->clearCookie('BEARER', '/');
		$response->headers->clearCookie('refresh_token', '/');
		return $response;
	}

	/**
	 * Delete every stored refresh token for a user — used on logout and after
	 * a password change so old sessions can't be resurrected via refresh
	 */
	private function revokeRefreshTokens(string $username): void
	{
		$this->entityManager->createQueryBuilder()
			->delete(RefreshToken::class, 'r')
			->where('r.username = :username')
			->setParameter('username', $username)
			->getQuery()
			->execute();
	}

	public function PostAction(User $data): User
	{
		if ($this->userService->checkUserLimit() === true) {
			throw new UserLimitReachedException;
		}
		$provider = $this->userService->getBuiltinProvider();
		// Friendly duplicate-username error instead of a raw SQL unique-key
		// 500 from the username_provider constraint. Scoped to create only;
		// the PUT flow (which reuses the same username) must not trip this.
		try {
			$this->userService->getUser((string)$data->getUsername(), $provider);
			throw new UnprocessableEntityHttpException('This username is already taken.');
		} catch (NoResultException) {
			// Good — username is free
		}
		// Prior to this guard an empty newPassword hit the PasswordHasher
		// with null and blew up with a cryptic TypeError further downstream;
		// enforce it as a validation error at the entry point instead
		$newPassword = $data->getNewPassword();
		if ($newPassword === null || $newPassword === '') {
			throw new UnprocessableEntityHttpException('A password is required when creating a new user.');
		}
		$data->setProvider($provider)
			->setPassword($this->userPasswordHasher->hashPassword($data, $newPassword))
			->setNewPassword(null);
		$this->entityManager->flush();

		return $data;
	}

//	public function GetProvidersAction()
//	{
//		//@todo
//	}

	public function getAction(User $data): User
	{
		$user = $this->getUser();
		if ($user instanceof User && $user->getId() === $data->getId()) {
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

		// Restrict to the user write group; roles, the password hash and its
		// change stamp are not writable here
		$data = $this->serializer->deserialize($request->getContent(), User::class, $request->attributes->get('_api_format') ?? $request->getRequestFormat(), [AbstractNormalizer::OBJECT_TO_POPULATE => $data, 'groups' => ['user:write']]);
		if ($data->isActive() && $this->userService->checkUserLimit()) {
			throw new UserLimitReachedException;
		}
		// Only an actually entered password replaces the stored one. The editor
		// sends an empty newPassword when the admin just edits other fields;
		// hashing that used to overwrite the user's password with the hash of
		// an empty string, locking them out
		$newPassword = $data->getNewPassword();
		$data->setNewPassword(null);
		$passwordChanged = $newPassword !== null && $newPassword !== '';
		if ($passwordChanged) {
			$data->setPassword($this->userPasswordHasher->hashPassword($data, $newPassword));
		}
		$this->entityManager->flush();
		// An admin resetting a user's password force-logs them out everywhere
		if ($passwordChanged) {
			$this->revokeRefreshTokens($data->getUserIdentifier());
		}
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
		if (!($limas['auth'] && ($limas['auth']['allow_password_change'] ?? false))) {
			throw new PasswordChangeNotAllowedException;
		}

		$decoded = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
		if (!isset($decoded['oldpassword']) || !isset($decoded['newpassword'])
			|| 0 === Strings::length($decoded['oldpassword']) || 0 === Strings::length($decoded['newpassword'])
		) {
			throw new \RuntimeException('old password and new password need to be specified');
		}

		if (!$this->userPasswordHasher->isPasswordValid($data, $decoded['oldpassword'])) {
			throw new OldPasswordWrongException;
		}

		$data->setPassword($this->userPasswordHasher->hashPassword($data, $decoded['newpassword']));
		$this->entityManager->persist($data);
		$this->entityManager->flush();
		// Changing your password invalidates outstanding refresh tokens (the JWTs already die via the passwordChangedAt claim)
		$this->revokeRefreshTokens($data->getUserIdentifier());

		return $data;
	}
}
