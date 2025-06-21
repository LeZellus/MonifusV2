<?php

namespace App\Repository;

use App\Entity\LotGroup;
use App\Entity\DofusCharacter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LotGroup>
 */
class LotGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LotGroup::class);
    }

    /**
     * @return LotGroup[]
     */
    public function findByCharacterOrderedByDate(DofusCharacter $character): array
    {
        return $this->createQueryBuilder('lg')
            ->where('lg.dofusCharacter = :character')
            ->setParameter('character', $character)
            ->orderBy('lg.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return LotGroup[]
     */
    public function findByCharacterWithItems(DofusCharacter $character): array
    {
        return $this->createQueryBuilder('lg')
            ->leftJoin('lg.item', 'i')
            ->addSelect('i')
            ->where('lg.dofusCharacter = :character')
            ->setParameter('character', $character)
            ->orderBy('lg.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByIdAndCharacter(int $id, DofusCharacter $character): ?LotGroup
    {
        return $this->createQueryBuilder('lg')
            ->where('lg.id = :id')
            ->andWhere('lg.dofusCharacter = :character')
            ->setParameter('id', $id)
            ->setParameter('character', $character)
            ->getQuery()
            ->getOneOrNullResult();
    }
}