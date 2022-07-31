<?php

namespace Limas\Repository;

use Limas\Entity\User;
use Limas\Entity\UserPreference;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;


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

	/**
	 * @throws ORMException
	 * @throws OptimisticLockException
	 */
	public function add(UserPreference $entity, bool $flush = true): void
	{
		$this->_em->persist($entity);
		if ($flush) {
			$this->_em->flush();
		}
	}

	/**
	 * @throws ORMException
	 * @throws OptimisticLockException
	 */
	public function remove(UserPreference $entity, bool $flush = true): void
	{
		$this->_em->remove($entity);
		if ($flush) {
			$this->_em->flush();
		}
	}

	// /**
	//  * @return UserPreference[] Returns an array of UserPreference objects
	//  */
	/*
	public function findByExampleField($value)
	{
		return $this->createQueryBuilder('u')
			->andWhere('u.exampleField = :val')
			->setParameter('val', $value)
			->orderBy('u.id', 'ASC')
			->setMaxResults(10)
			->getQuery()
			->getResult()
		;
	}
	*/

	/*
	public function findOneBySomeField($value): ?UserPreference
	{
		return $this->createQueryBuilder('u')
			->andWhere('u.exampleField = :val')
			->setParameter('val', $value)
			->getQuery()
			->getOneOrNullResult()
		;
	}
	*/
	public function getPreferences(User $user): array
	{
		return $this->findBy(['user' => $user]);
	}
}
