<?php

// src/Repository/LotGroupRepository.php
namespace App\Repository;

use App\Entity\LotGroup;
use App\Entity\DofusCharacter;
use App\Enum\LotStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LotGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LotGroup::class);
    }

    // Méthodes existantes...
    public function findByCharacterOrderedByDate(DofusCharacter $character): array
    {
        return $this->createQueryBuilder('lg')
            ->where('lg.dofusCharacter = :character')
            ->setParameter('character', $character)
            ->orderBy('lg.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

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

    // NOUVELLES MÉTHODES POUR HOMECONTROLLER
    public function getTotalInvestedAmount(): int
    {
        return (int) ($this->createQueryBuilder('lg')
            ->select('SUM(lg.buyPricePerLot * lg.lotSize)')
            ->where('lg.status = :available')
            ->setParameter('available', LotStatus::AVAILABLE)
            ->getQuery()
            ->getSingleScalarResult() ?? 0);
    }

    public function getTotalPotentialProfit(): int
    {
        return (int) ($this->createQueryBuilder('lg')
            ->select('SUM((lg.sellPricePerLot - lg.buyPricePerLot) * lg.lotSize)')
            ->where('lg.status = :available')
            ->setParameter('available', LotStatus::AVAILABLE)
            ->getQuery()
            ->getSingleScalarResult() ?? 0);
    }

    public function getTotalLotsManaged(): int
    {
        return (int) ($this->createQueryBuilder('lg')
            ->select('SUM(lg.lotSize)')
            ->getQuery()
            ->getSingleScalarResult() ?? 0);
    }

    public function getGlobalStatistics(): array
    {
        $qb = $this->createQueryBuilder('lg');
        
        $result = $qb
            ->select([
                'SUM(CASE WHEN lg.status = :available THEN lg.buyPricePerLot * lg.lotSize ELSE 0 END) as totalInvested',
                'SUM(CASE WHEN lg.status = :available THEN (lg.sellPricePerLot - lg.buyPricePerLot) * lg.lotSize ELSE 0 END) as totalPotentialProfit',
                'SUM(lg.lotSize) as totalLotsManaged'
            ])
            ->setParameter('available', LotStatus::AVAILABLE)
            ->getQuery()
            ->getSingleResult();

        return [
            'total_invested' => (int) ($result['totalInvested'] ?? 0),
            'total_potential_profit' => (int) ($result['totalPotentialProfit'] ?? 0),
            'total_lots_managed' => (int) ($result['totalLotsManaged'] ?? 0)
        ];
    }
}