<?php

namespace Limas\Repository;

use Limas\Entity\SystemNotice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;


/**
 * @method SystemNotice|null find($id, $lockMode = null, $lockVersion = null)
 * @method SystemNotice|null findOneBy(array $criteria, array $orderBy = null)
 * @method SystemNotice[]    findAll()
 * @method SystemNotice[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SystemNoticeRepository
	extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SystemNotice::class);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(SystemNotice $entity, bool $flush = true): void
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
    public function remove(SystemNotice $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    // /**
    //  * @return SystemNotice[] Returns an array of SystemNotice objects
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
    public function findOneBySomeField($value): ?SystemNotice
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
