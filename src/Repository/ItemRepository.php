<?php

namespace App\Repository;

use App\Entity\Item;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Item>
 */
class ItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Item::class);
    }

    public function searchByName(string $query, int $limit = 20): array
    {
        return $this->createQueryBuilder('i')
            ->select('i.id, i.name, i.level, i.itemType')
            ->where('i.name LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->setMaxResults($limit)
            ->orderBy('i.name', 'ASC')
            ->getQuery()
            ->getArrayResult(); // ✅ Déjà optimisé avec arrayResult
    }

    public function searchResourcesByName(string $query, int $limit = 20): array
    {
        return $this->createQueryBuilder('i')
            ->select('i.id, i.name, i.level, i.itemType')
            ->where('i.name LIKE :query')
            ->andWhere('i.itemType = :resourceType OR i.itemType IS NULL')
            ->setParameter('query', '%' . $query . '%')
            ->setParameter('resourceType', ItemType::RESOURCE->value)
            ->setMaxResults($limit)
            ->orderBy('i.name', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }
}
