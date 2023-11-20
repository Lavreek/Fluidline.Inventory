<?php

namespace App\Repository\Inventory;

use App\Entity\Inventory\Inventory;
use App\Entity\Inventory\InventoryParamhouse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
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

    public function codeSearch(string $code) : array|bool|null
    {
        try {
            $query = $this->createQueryBuilder('i');

            return $query
                ->select("i.code", "i.serial")
                ->where($query->expr()->like("i.code", ':code'))
                ->setParameter('code', "%$code%")
                ->setMaxResults(10)
                ->getQuery()
                ->getResult()
            ;

        } catch (NoResultException) {
            return false;

        } catch (NonUniqueResultException) {
            return null;
        }
    }

    public function productsSearch(string $code, int $limit) : array|bool|null
    {
        try {
            $query = $this->createQueryBuilder('i');

            return $query
                ->where($query->expr()->like("i.code", ':code'))
                ->setParameter('code', "%$code%")
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult()
                ;

        } catch (NoResultException) {
            return false;

        } catch (NonUniqueResultException) {
            return null;
        }
    }

    /**
     * @return Inventory[]|null Returns an array of Inventory objects
     */
    public function getSerialExist(string $type, string $serial) : array|bool|null
    {
        try {
            return $this->createQueryBuilder('i')
                ->distinct()
                ->select("i.serial", "i.type")
                ->where("i.serial = :serial")
                ->andWhere("i.type = :type")
                ->setParameter('serial', $serial)
                ->setParameter('type', $type)
                ->setMaxResults(1)
                ->getQuery()
                ->getSingleResult();

        } catch (NoResultException) {
            return false;

        } catch (NonUniqueResultException) {
            return null;
        }
    }

    /**
     * @return Inventory[] Returns an array of Inventory objects
     */
    public function getDistinctTypes(): array
    {
        return $this->createQueryBuilder('i')
            ->distinct()
            ->select('i.type')
            ->getQuery()
            ->getResult()
            ;
    }

    /**
     * @return Inventory[] Returns an array of Inventory objects
     */
    public function getDistinctTypeSerials(string $type): array
    {
        return $this->createQueryBuilder('i')
            ->distinct()
            ->select("i.serial")
            ->where("i.type = :type")
            ->setParameter('type', $type)
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
    public function findByOrder($serial, $order, $limit): array
    {
        $query = $this->createQueryBuilder('i')
            ->select('i')
            ->andWhere('i.serial = :serial')
            ->setParameter('serial', $serial);

        $this->setOrderParameters($query, $order);

        return $query
            ->orderBy('i.id', 'ASC')
            ->setMaxResults($limit)
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

    /**
     * @return Inventory[] Returns an array of Inventory objects
     */
    public function getSerialFilter($serial): array
    {
        return $this->createQueryBuilder('i')
            ->distinct()
            ->select('ip.name', 'ip.value', 'ip.description')
            ->andWhere('i.serial = :serial')
            ->setParameter('serial', $serial)
            ->innerJoin(InventoryParamhouse::class, 'ip', Expr\Join::WITH, 'i.id = ip.code')
            ->orderBy('ip.name', 'ASC')
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
