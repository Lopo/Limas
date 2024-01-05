<?php

namespace Limas\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Limas\Entity\User;
use Limas\Entity\UserPreference;


/**
 * @method UserPreference|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserPreference[]    findAll()
 * @method UserPreference[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserPreferenceRepository
	extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, UserPreference::class);
	}

	public function add(UserPreference $entity, bool $flush = true): void
	{
		$this->_em->persist($entity);
		if ($flush) {
			$this->_em->flush();
		}
	}

	public function remove(UserPreference $entity, bool $flush = true): void
	{
		$this->_em->remove($entity);
		if ($flush) {
			$this->_em->flush();
		}
	}

	public function getPreferences(User $user): array
	{
		return $this->findBy(['user' => $user]);
	}
}
