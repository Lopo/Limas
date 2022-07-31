<?php

namespace Limas\Repository;

use Limas\Entity\PartManufacturer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;


/**
 * @method PartManufacturer|null find($id, $lockMode = null, $lockVersion = null)
 * @method PartManufacturer|null findOneBy(array $criteria, array $orderBy = null)
 * @method PartManufacturer[]    findAll()
 * @method PartManufacturer[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PartManufacturerRepository
	extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PartManufacturer::class);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(PartManufacturer $entity, bool $flush = true): void
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
    public function remove(PartManufacturer $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    // /**
    //  * @return PartManufacturer[] Returns an array of PartManufacturer objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('p.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?PartManufacturer
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
