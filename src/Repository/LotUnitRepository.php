<?php

// src/Repository/LotUnitRepository.php
namespace App\Repository;

use App\Entity\LotUnit;
use App\Entity\User;
use App\Entity\DofusCharacter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LotUnitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LotUnit::class);
    }

    // Méthodes existantes...
    public function findSalesWithFilters(
        User $user, 
        ?DofusCharacter $character = null, 
        ?string $period = null
    ): array {
        $qb = $this->createQueryBuilder('lu')
            ->join('lu.lotGroup', 'lg')
            ->join('lg.dofusCharacter', 'c')
            ->join('lg.item', 'i')
            ->join('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->setParameter('user', $user);

        if ($character) {
            $qb->andWhere('c = :character')
            ->setParameter('character', $character);
        }

        if ($period && $period !== 'all') {
            $date = new \DateTime();
            $date->modify("-{$period} days");
            $qb->andWhere('lu.soldAt >= :date')
            ->setParameter('date', $date);
        }

        return $qb->orderBy('lu.soldAt', 'DESC')
                ->getQuery()
                ->getResult();
    }

    public function calculateSalesStats(array $sales): array
    {
        $totalRealizedProfit = 0;
        $totalExpectedProfit = 0;
        
        foreach ($sales as $sale) {
            $realizedProfit = ($sale->getActualSellPrice() - $sale->getLotGroup()->getBuyPricePerLot()) * $sale->getQuantitySold();
            $expectedProfit = ($sale->getLotGroup()->getSellPricePerLot() - $sale->getLotGroup()->getBuyPricePerLot()) * $sale->getQuantitySold();
            
            $totalRealizedProfit += $realizedProfit;
            $totalExpectedProfit += $expectedProfit;
        }

        return [
            'total_realized_profit' => $totalRealizedProfit,
            'total_expected_profit' => $totalExpectedProfit,
            'profit_difference' => $totalRealizedProfit - $totalExpectedProfit
        ];
    }

    // NOUVELLE MÉTHODE POUR HOMECONTROLLER
    public function getTotalRealizedProfits(): int
    {
        return (int) ($this->createQueryBuilder('lu')
            ->select('SUM((lu.actualSellPrice - lg.buyPricePerLot) * lu.quantitySold)')
            ->join('lu.lotGroup', 'lg')
            ->getQuery()
            ->getSingleScalarResult() ?? 0);
    }
}