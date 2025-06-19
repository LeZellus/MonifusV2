<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\MarketWatchRepository;
use App\Repository\LotGroupRepository;

class NotificationService
{
    public function __construct(
        private MarketWatchRepository $marketWatchRepository,
        private LotGroupRepository $lotGroupRepository
    ) {
    }

    public function getUserNotifications(User $user): array
    {
        $notifications = [];

        // Alerte : Lots disponibles depuis longtemps
        $oldAvailableLots = $this->lotGroupRepository->createQueryBuilder('lg')
            ->join('lg.dofusCharacter', 'c')
            ->join('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->andWhere('lg.status = :available')
            ->andWhere('lg.createdAt < :date')
            ->setParameter('user', $user)
            ->setParameter('available', \App\Enum\LotStatus::AVAILABLE)
            ->setParameter('date', new \DateTime('-30 days'))
            ->getQuery()
            ->getResult();

        if (count($oldAvailableLots) > 0) {
            $notifications[] = [
                'type' => 'warning',
                'icon' => '⏰',
                'title' => 'Lots anciens',
                'message' => count($oldAvailableLots) . ' lots disponibles depuis plus de 30 jours',
                'action' => 'Voir les lots',
                'link' => '/lot'
            ];
        }

        // Alerte : Beaucoup d'argent investi
        $totalInvested = $this->lotGroupRepository->createQueryBuilder('lg')
            ->select('SUM(lg.buyPricePerLot * lg.lotSize)')
            ->join('lg.dofusCharacter', 'c')
            ->join('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->andWhere('lg.status = :available')
            ->setParameter('user', $user)
            ->setParameter('available', \App\Enum\LotStatus::AVAILABLE)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        if ($totalInvested > 100000000) { // 100M kamas
            $notifications[] = [
                'type' => 'info',
                'icon' => '💰',
                'title' => 'Gros investissement',
                'message' => number_format($totalInvested / 1000000, 1) . 'M kamas investis en cours',
                'action' => 'Voir analytics',
                'link' => '/analytics'
            ];
        }

        // Suggestion : Surveillances à mettre à jour
        $oldWatches = $this->marketWatchRepository->createQueryBuilder('mw')
            ->join('mw.dofusCharacter', 'c')
            ->join('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->andWhere('mw.updatedAt < :date')
            ->setParameter('user', $user)
            ->setParameter('date', new \DateTime('-7 days'))
            ->getQuery()
            ->getResult();

        if (count($oldWatches) > 0) {
            $notifications[] = [
                'type' => 'info',
                'icon' => '📊',
                'title' => 'Surveillance obsolète',
                'message' => count($oldWatches) . ' prix non mis à jour depuis 7 jours',
                'action' => 'Mettre à jour',
                'link' => '/market-watch'
            ];
        }

        // Succès : Bonnes performances récentes
        $recentProfitableSales = $this->lotGroupRepository->createQueryBuilder('lg')
            ->join('lg.lotUnits', 'lu')
            ->join('lg.dofusCharacter', 'c')
            ->join('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->andWhere('lu.soldAt > :date')
            ->andWhere('(lu.actualSellPrice - lg.buyPricePerLot) > lg.buyPricePerLot * 0.5') // +50% profit
            ->setParameter('user', $user)
            ->setParameter('date', new \DateTime('-7 days'))
            ->getQuery()
            ->getResult();

        if (count($recentProfitableSales) > 0) {
            $notifications[] = [
                'type' => 'success',
                'icon' => '🎉',
                'title' => 'Bonnes performances !',
                'message' => count($recentProfitableSales) . ' ventes très rentables cette semaine',
                'action' => 'Voir historique',
                'link' => '/sales-history'
            ];
        }

        return $notifications;
    }
}