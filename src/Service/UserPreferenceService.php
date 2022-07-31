<?php

namespace Limas\Service;

use Doctrine\ORM\EntityManagerInterface;
use Limas\Entity\User;
use Limas\Entity\UserPreference;
use Limas\Exceptions\UserPreferenceNotFoundException;


class UserPreferenceService
{
	public function __construct(private readonly EntityManagerInterface $entityManager)
	{
	}

	public function setPreference(User $user, string $key, string $value): UserPreference
	{
		$userPreference = $this->entityManager->getRepository(UserPreference::class)->findOneBy(['user' => $user, 'preferenceKey' => $key]);
		if ($userPreference === null) {
			$this->entityManager->persist($userPreference = (new UserPreference($user, $key)));
		}

		$userPreference->setPreferenceValue($value);

		$this->entityManager->flush();

		return $userPreference;
	}

	public function getPreferenceValue(User $user, string $key): mixed
	{
		return $this->getPreference($user, $key)->getPreferenceValue();
	}

	public function getPreferences(User $user): array
	{
		return $this->entityManager->getRepository(UserPreference::class)->findBy(['user' => $user]);
	}

	public function getPreference(User $user, string $key): UserPreference
	{
		$pref = $this->entityManager->getRepository(UserPreference::class)->findOneBy(['user' => $user, 'preferenceKey' => $key]);
		if ($pref === null) {
			throw new UserPreferenceNotFoundException($user, $key);
		}
		return $pref;
	}

	public function deletePreference(User $user, string $key): void
	{
		$qb = $this->entityManager->createQueryBuilder();
		$qb->delete(UserPreference::class, 'up')
			->andWhere($qb->expr()->eq('up.user', ':user'))
			->andWhere($qb->expr()->eq('up.preferenceKey', ':key'))
			->setParameter('user', $user)
			->setParameter('key', $key)
			->getQuery()->execute();
	}

	public function deletePreferences(User $user): void
	{
		$qb = $this->entityManager->createQueryBuilder();
		$qb->delete(UserPreference::class, 'up')
			->andWhere($qb->expr()->eq('up.user', ':user'))
			->setParameter('user', $user)
			->getQuery()->execute();
	}
}
