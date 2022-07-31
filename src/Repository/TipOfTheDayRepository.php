<?php

namespace Limas\Repository;

use Limas\Entity\TipOfTheDay;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;


/**
 * @method TipOfTheDay|null find($id, $lockMode = null, $lockVersion = null)
 * @method TipOfTheDay|null findOneBy(array $criteria, array $orderBy = null)
 * @method TipOfTheDay[]    findAll()
 * @method TipOfTheDay[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TipOfTheDayRepository
	extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TipOfTheDay::class);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(TipOfTheDay $entity, bool $flush = true): void
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
    public function remove(TipOfTheDay $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    // /**
    //  * @return TipOfTheDay[] Returns an array of TipOfTheDay objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('t.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?TipOfTheDay
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
