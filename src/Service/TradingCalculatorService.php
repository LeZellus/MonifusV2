<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\LotGroupRepository;
use App\Repository\DofusCharacterRepository;
use App\Enum\LotStatus;

class TradingCalculatorService
{
    public function __construct(
        private LotGroupRepository $lotGroupRepository,
        private DofusCharacterRepository $characterRepository
    ) {
    }

    public function getUserTradingStats(User $user): array
    {
        // Récupérer tous les personnages de l'utilisateur
        $characters = $this->characterRepository->createQueryBuilder('c')
            ->join('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        if (empty($characters)) {
            return $this->getEmptyStats();
        }

        // Statistiques globales
        $globalStats = $this->lotGroupRepository->createQueryBuilder('lg')
            ->select([
                'COUNT(lg.id) as totalLots',
                'SUM(CASE WHEN lg.status = :available THEN 1 ELSE 0 END) as availableLots',
                'SUM(CASE WHEN lg.status = :sold THEN 1 ELSE 0 END) as soldLots',
                'SUM(CASE WHEN lg.status = :available THEN lg.buyPricePerLot * lg.lotSize ELSE 0 END) as investedAmount',
                'SUM(CASE WHEN lg.status = :sold THEN (lg.sellPricePerLot - lg.buyPricePerLot) * lg.lotSize ELSE 0 END) as realizedProfit',
                'SUM(CASE WHEN lg.status = :available THEN (lg.sellPricePerLot - lg.buyPricePerLot) * lg.lotSize ELSE 0 END) as potentialProfit'
            ])
            ->join('lg.dofusCharacter', 'c')
            ->join('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->setParameter('user', $user)
            ->setParameter('available', LotStatus::AVAILABLE)
            ->setParameter('sold', LotStatus::SOLD)
            ->getQuery()
            ->getOneOrNullResult();

        // Top 5 des items les plus rentables
        $topItems = $this->lotGroupRepository->createQueryBuilder('lg')
            ->select([
                'i.name as itemName',
                'COUNT(lg.id) as lotsCount',
                'SUM((lg.sellPricePerLot - lg.buyPricePerLot) * lg.lotSize) as totalProfit'
            ])
            ->join('lg.item', 'i')
            ->join('lg.dofusCharacter', 'c')
            ->join('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->setParameter('user', $user)
            ->groupBy('i.id', 'i.name')
            ->orderBy('totalProfit', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        return [
            'global' => $globalStats ?: $this->getEmptyStats()['global'],
            'topItems' => $topItems,
            'charactersCount' => count($characters)
        ];
    }

    private function getEmptyStats(): array
    {
        return [
            'global' => [
                'totalLots' => 0,
                'availableLots' => 0,
                'soldLots' => 0,
                'investedAmount' => 0,
                'realizedProfit' => 0,
                'potentialProfit' => 0
            ],
            'topItems' => [],
            'charactersCount' => 0
        ];
    }
}