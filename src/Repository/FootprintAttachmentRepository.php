<?php

namespace Limas\Repository;

use Limas\Entity\FootprintAttachment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;


/**
 * @method FootprintAttachment|null find($id, $lockMode = null, $lockVersion = null)
 * @method FootprintAttachment|null findOneBy(array $criteria, array $orderBy = null)
 * @method FootprintAttachment[]    findAll()
 * @method FootprintAttachment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FootprintAttachmentRepository
	extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FootprintAttachment::class);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(FootprintAttachment $entity, bool $flush = true): void
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
    public function remove(FootprintAttachment $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    // /**
    //  * @return FootprintAttachment[] Returns an array of FootprintAttachment objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('f.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?FootprintAttachment
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
