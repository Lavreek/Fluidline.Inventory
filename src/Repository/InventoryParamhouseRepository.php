<?php

namespace App\Repository;

use App\Entity\InventoryParamhouse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InventoryParamhouse>
 *
 * @method InventoryParamhouse|null find($id, $lockMode = null, $lockVersion = null)
 * @method InventoryParamhouse|null findOneBy(array $criteria, array $orderBy = null)
 * @method InventoryParamhouse[]    findAll()
 * @method InventoryParamhouse[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InventoryParamhouseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InventoryParamhouse::class);
    }

    public function findByParameters($keys, $values) : array
    {
        return $this->createQueryBuilder('p')
            ->select('identity(p.code)')
            ->andWhere("p.name in ('". implode("','", $keys) ."')")
            ->andWhere("p.value in ('". implode("','", $values) ."')")
            ->groupBy('p.code')
            ->having("COUNT(p.code) = ". count($keys))
            ->setMaxResults(100)
            ->getQuery()
            ->getResult()
        ;
    }

//    /**
//     * @return InventoryParamhouse[] Returns an array of InventoryParamhouse objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('i')
//            ->andWhere('i.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('i.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?InventoryParamhouse
//    {
//        return $this->createQueryBuilder('i')
//            ->andWhere('i.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
