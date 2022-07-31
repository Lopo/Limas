<?php

namespace Limas\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Limas\Entity\PartMeasurementUnit;


/**
 * @extends ServiceEntityRepository<PartMeasurementUnit>
 *
 * @method PartMeasurementUnit|null find($id, $lockMode = null, $lockVersion = null)
 * @method PartMeasurementUnit|null findOneBy(array $criteria, array $orderBy = null)
 * @method PartMeasurementUnit[]    findAll()
 * @method PartMeasurementUnit[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PartMeasurementUnitRepository
	extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PartMeasurementUnit::class);
    }

    public function add(PartMeasurementUnit $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PartMeasurementUnit $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return PartMeasurementUnit[] Returns an array of PartMeasurementUnit objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?PartMeasurementUnit
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
