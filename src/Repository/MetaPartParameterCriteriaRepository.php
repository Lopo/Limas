<?php

namespace Limas\Repository;

use Limas\Entity\MetaPartParameterCriteria;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;


/**
 * @method MetaPartParameterCriteria|null find($id, $lockMode = null, $lockVersion = null)
 * @method MetaPartParameterCriteria|null findOneBy(array $criteria, array $orderBy = null)
 * @method MetaPartParameterCriteria[]    findAll()
 * @method MetaPartParameterCriteria[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MetaPartParameterCriteriaRepository
	extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MetaPartParameterCriteria::class);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(MetaPartParameterCriteria $entity, bool $flush = true): void
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
    public function remove(MetaPartParameterCriteria $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    // /**
    //  * @return MetaPartParameterCriteria[] Returns an array of MetaPartParameterCriteria objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('m.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?MetaPartParameterCriteria
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
