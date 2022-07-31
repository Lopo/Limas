<?php

namespace Limas\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NoResultException;
use Limas\Entity\User;
use Limas\Entity\UserProvider;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;


class UserService
{
	public const BUILTIN_PROVIDER = 'Builtin';


	public function __construct(
		private readonly TokenStorageInterface  $tokenStorage,
		private readonly EntityManagerInterface $entityManager,
		private readonly bool|int               $userLimit = false
	)
	{
	}

	/**
	 * Returns the User based on the user token within the Symfony environment
	 */
	public function getCurrentUser(): User
	{
		return $this->tokenStorage->getToken()->getUser();

		$token = $this->tokenStorage->getToken();
		$tokenProvider = $this->tokenStorage->getToken()->getAttribute('provider');
		$provider = $this->getProvider($tokenProvider);
		$username = $this->tokenStorage->getToken()->getUsername();

		return $this->getUser($username, $provider, true);
	}

	public function getProvider(string $providerClass): UserProvider
	{
		return $this->getProviderByType($this->getProviderTypeByClass($providerClass));
	}

	public function getProviderTypeByClass(string $providerClass): string
	{
		return self::BUILTIN_PROVIDER; // for now only internal
//		return match ($providerClass) {
//			'Symfony\Component\Security\Core\Authentication\Provider\DaoAuthenticationProvider', 'Escape\WSSEAuthenticationBundle\Security\Core\Authentication\Provider\Provider' => self::BUILTIN_PROVIDER,
//			'FR3D\LdapBundle\Security\Authentication\LdapAuthenticationProvider' => 'LDAP',
//			default => throw new \Exception('Unknown provider ' . $providerClass),
//		};
	}

	public function getProviderByType($type): UserProvider
	{
		$provider = $this->entityManager->getRepository(UserProvider::class)->findOneBy(['type' => $type]);

		if ($provider !== null) {
			return $provider;
		}

		$provider = new UserProvider($type, $type === self::BUILTIN_PROVIDER);

		$this->entityManager->persist($provider);
		$this->entityManager->flush();

		return $provider;
	}

	public function getBuiltinProvider(): UserProvider
	{
		return $this->getProviderByType(self::BUILTIN_PROVIDER);
	}

	public function getUser(string $username, UserProvider $provider, bool $create = false): User
	{
		$qb = $this->entityManager->createQueryBuilder();
		$qb->select('u')
			->from(User::class, 'u')
			->andWhere($qb->expr()->eq('u.provider', ':provider'))
			->andWhere($qb->expr()->eq('u.username', ':username'))
			->setParameter('provider', $provider)
			->setParameter('username', $username);

		try {
			return $qb->getQuery()->getSingleResult();
		} catch (NoResultException $e) {
			if ($create === false) {
				throw $e;
			}
			return $this->createUser($username, $provider);
		}
	}

	private function createUser(string $username, UserProvider $provider): User
	{
		$user = (new User($username, $provider))
			->setProtected(false)
			->setActive(true);
		$this->entityManager->persist($user);
		$this->entityManager->flush();

		return $user;
	}

	/**
	 * Protects a given user against changes
	 */
	public function protect(User $user): void
	{
		$user->setProtected(true);
		$this->entityManager->flush();
	}

	/**
	 * Unprotects a given user against changes
	 */
	public function unprotect(User $user): void
	{
		$user->setProtected(false);
		$this->entityManager->flush();
	}

	/**
	 * Returns the number of users present in the system
	 */
	public function getUserCount(): int
	{
		$qb = $this->entityManager->getRepository(User::class)->createQueryBuilder('u');
		return $qb->select($qb->expr()->count('u'))
//			->where($qb->expr()->eq('u.active', true))
			->getQuery()->getSingleScalarResult();
	}

	public function checkUserLimit(): bool
	{
		return ($this->userLimit !== false) && $this->getUserCount() >= $this->userLimit;
	}
}
