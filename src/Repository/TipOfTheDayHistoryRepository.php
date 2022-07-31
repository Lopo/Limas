<?php

namespace Limas\Repository;

use Limas\Entity\TipOfTheDayHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;


/**
 * @method TipOfTheDayHistory|null find($id, $lockMode = null, $lockVersion = null)
 * @method TipOfTheDayHistory|null findOneBy(array $criteria, array $orderBy = null)
 * @method TipOfTheDayHistory[]    findAll()
 * @method TipOfTheDayHistory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TipOfTheDayHistoryRepository
	extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TipOfTheDayHistory::class);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(TipOfTheDayHistory $entity, bool $flush = true): void
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
    public function remove(TipOfTheDayHistory $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    // /**
    //  * @return TipOfTheDayHistory[] Returns an array of TipOfTheDayHistory objects
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
    public function findOneBySomeField($value): ?TipOfTheDayHistory
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
