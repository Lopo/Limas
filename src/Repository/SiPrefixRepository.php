<?php

namespace Limas\Repository;

use Limas\Entity\SiPrefix;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;


/**
 * @method SiPrefix|null find($id, $lockMode = null, $lockVersion = null)
 * @method SiPrefix|null findOneBy(array $criteria, array $orderBy = null)
 * @method SiPrefix[]    findAll()
 * @method SiPrefix[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SiPrefixRepository
	extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SiPrefix::class);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(SiPrefix $entity, bool $flush = true): void
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
    public function remove(SiPrefix $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    // /**
    //  * @return SiPrefix[] Returns an array of SiPrefix objects
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
    public function findOneBySomeField($value): ?SiPrefix
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
