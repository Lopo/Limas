<?php

namespace Limas\Repository;

use Limas\Entity\BatchJobUpdateField;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;


/**
 * @method BatchJobUpdateField|null find($id, $lockMode = null, $lockVersion = null)
 * @method BatchJobUpdateField|null findOneBy(array $criteria, array $orderBy = null)
 * @method BatchJobUpdateField[]    findAll()
 * @method BatchJobUpdateField[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BatchJobUpdateFieldRepository
	extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BatchJobUpdateField::class);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(BatchJobUpdateField $entity, bool $flush = true): void
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
    public function remove(BatchJobUpdateField $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    // /**
    //  * @return BatchJobUpdateField[] Returns an array of BatchJobUpdateField objects
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
    public function findOneBySomeField($value): ?BatchJobUpdateField
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
