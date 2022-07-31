<?php

namespace Limas\Repository;

use Limas\Entity\SystemPreference;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;


/**
 * @method SystemPreference|null find($id, $lockMode = null, $lockVersion = null)
 * @method SystemPreference|null findOneBy(array $criteria, array $orderBy = null)
 * @method SystemPreference[]    findAll()
 * @method SystemPreference[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SystemPreferenceRepository
	extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SystemPreference::class);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(SystemPreference $entity, bool $flush = true): void
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
    public function remove(SystemPreference $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    // /**
    //  * @return SystemPreference[] Returns an array of SystemPreference objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('s.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?SystemPreference
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
