<?php

namespace App\Repository;

use App\Entity\Inventory;
use App\Entity\InventoryParamhouse;
use App\Service\QueueBuilder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Inventory>
 *
 * @method Inventory|null find($id, $lockMode = null, $lockVersion = null)
 * @method Inventory|null findOneBy(array $criteria, array $orderBy = null)
 * @method Inventory[]    findAll()
 * @method Inventory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InventoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Inventory::class);
    }

    /**
     * @return Inventory[] Returns an array of Inventory objects
     */
    public function distinctSerial(): array
    {
        return $this->createQueryBuilder('i')
            ->distinct()
            ->select("i.serial")
            ->setMaxResults(100)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return Inventory[] Returns an array of Inventory objects
     */
    public function findBySerial($serial): array
    {
        return $this->createQueryBuilder('i')
            ->select("i")
            ->where('i.serial = :serial')
            ->setParameter('serial', $serial)
            ->setMaxResults(100)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return Inventory[] Returns an array of Inventory objects
     */
    public function findByOrder($serial, $order): array
    {
        $query = $this->createQueryBuilder('i')
            ->select('i')
            ->andWhere('i.serial = :serial')
            ->setParameter('serial', $serial);

        $this->setOrderParameters($query, $order);

        return $query
            ->orderBy('i.id', 'ASC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult()
        ;
    }

    private function setOrderParameters(QueryBuilder &$query, $order)
    {
        $manager = $this->getEntityManager();

        $havingKeys = $havingValues = [];

        if (!is_null($order)) {
            foreach ($order as $item) {
                if (!in_array($item['key'], $havingKeys)) {
                    $havingKeys[] = $item['key'];
                }

                if (!in_array($item['value'], $havingValues)) {
                    $havingValues[] = $item['value'];
                }
            }
        }

        /** @var InventoryParamhouseRepository $paramhouse */
        $paramhouse = $manager->getRepository(InventoryParamhouse::class);

        if (!empty($havingKeys) and !empty($havingValues)) {
            $ids = $paramhouse->findByParameters($havingKeys, $havingValues);

            $query->andWhere("i.id IN ($ids)");
        }
    }

    public function removeBySerialType(string $serial, string $type) : void
    {
        $this->createQueryBuilder('i')
            ->delete(Inventory::class, 'i')
            ->where('i.serial = :s')
            ->setParameter('s', $serial)
            ->andWhere('i.type = :t')
            ->setParameter('t', $type)
            ->getQuery()
            ->getResult()
        ;
    }

//    /**
//     * @return Inventory[] Returns an array of Inventory objects
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

//    public function findOneBySomeField($value): ?Inventory
//    {
//        return $this->createQueryBuilder('i')
//            ->andWhere('i.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
