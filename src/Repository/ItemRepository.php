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
        $qb = $this->createQueryBuilder('i')
            ->select('i.id, i.name, i.level, i.itemType, i.imgUrl')
            ->where('i.name LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->setMaxResults($limit);

        // TRI PAR PERTINENCE avec CASE WHEN pour MySQL
        $qb->addSelect('
            CASE 
                WHEN LOWER(i.name) = LOWER(:exactQuery) THEN 100
                WHEN LOWER(i.name) LIKE LOWER(:startsQuery) THEN 80
                WHEN LOWER(i.name) LIKE LOWER(:containsQuery) THEN 10
                ELSE 0
            END as HIDDEN relevance_score
        ')
        ->setParameter('exactQuery', $query)
        ->setParameter('startsQuery', $query . '%')
        ->setParameter('containsQuery', '%' . $query . '%')
        ->orderBy('relevance_score', 'DESC')
        ->addOrderBy('i.name', 'ASC'); // Tri alphabétique en cas d'égalité

        return $qb->getQuery()->getArrayResult();
    }

    public function searchResourcesByName(string $query, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('i')
            ->select('i.id, i.name, i.level, i.itemType, i.imgUrl')
            ->where('i.name LIKE :query')
            ->andWhere('i.itemType = :resourceType OR i.itemType IS NULL')
            ->setParameter('query', '%' . $query . '%')
            ->setParameter('resourceType', ItemType::RESOURCE->value)
            ->setMaxResults($limit);

        // TRI PAR PERTINENCE pour les ressources aussi
        $qb->addSelect('
            CASE 
                WHEN LOWER(i.name) = LOWER(:exactQuery) THEN 100
                WHEN LOWER(i.name) LIKE LOWER(:startsQuery) THEN 80
                WHEN LOWER(i.name) LIKE LOWER(:containsQuery) THEN 10
                ELSE 0
            END as HIDDEN relevance_score
        ')
        ->setParameter('exactQuery', $query)
        ->setParameter('startsQuery', $query . '%')
        ->setParameter('containsQuery', '%' . $query . '%')
        ->orderBy('relevance_score', 'DESC')
        ->addOrderBy('i.name', 'ASC');

        return $qb->getQuery()->getArrayResult();
    }
}
