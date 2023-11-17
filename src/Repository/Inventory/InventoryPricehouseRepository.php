<?php

namespace App\Repository\Inventory;

use App\Entity\Inventory\InventoryPricehouse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InventoryPricehouse>
 *
 * @method InventoryPricehouse|null find($id, $lockMode = null, $lockVersion = null)
 * @method InventoryPricehouse|null findOneBy(array $criteria, array $orderBy = null)
 * @method InventoryPricehouse[]    findAll()
 * @method InventoryPricehouse[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InventoryPricehouseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InventoryPricehouse::class);
    }

//    /**
//     * @return InventoryPricehouse[] Returns an array of InventoryPricehouse objects
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

//    public function findOneBySomeField($value): ?InventoryPricehouse
//    {
//        return $this->createQueryBuilder('i')
//            ->andWhere('i.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
