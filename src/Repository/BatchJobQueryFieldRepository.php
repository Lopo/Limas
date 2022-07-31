<?php

namespace Limas\Repository;

use Limas\Entity\BatchJobQueryField;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;


/**
 * @method BatchJobQueryField|null find($id, $lockMode = null, $lockVersion = null)
 * @method BatchJobQueryField|null findOneBy(array $criteria, array $orderBy = null)
 * @method BatchJobQueryField[]    findAll()
 * @method BatchJobQueryField[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BatchJobQueryFieldRepository
	extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BatchJobQueryField::class);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(BatchJobQueryField $entity, bool $flush = true): void
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
    public function remove(BatchJobQueryField $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    // /**
    //  * @return BatchJobQueryField[] Returns an array of BatchJobQueryField objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('b.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?BatchJobQueryField
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
