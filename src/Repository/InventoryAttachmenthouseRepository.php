<?php

namespace App\Repository;

use App\Entity\InventoryAttachmenthouse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InventoryAttachmenthouse>
 *
 * @method InventoryAttachmenthouse|null find($id, $lockMode = null, $lockVersion = null)
 * @method InventoryAttachmenthouse|null findOneBy(array $criteria, array $orderBy = null)
 * @method InventoryAttachmenthouse[]    findAll()
 * @method InventoryAttachmenthouse[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InventoryAttachmenthouseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InventoryAttachmenthouse::class);
    }

//    /**
//     * @return InventoryAttachmenthouse[] Returns an array of InventoryAttachmenthouse objects
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

//    public function findOneBySomeField($value): ?InventoryAttachmenthouse
//    {
//        return $this->createQueryBuilder('i')
//            ->andWhere('i.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
