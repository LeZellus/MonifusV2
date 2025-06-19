<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\LotGroupRepository;
use App\Repository\LotUnitRepository;

class WidgetService
{
    public function __construct(
        private LotGroupRepository $lotGroupRepository,
        private LotUnitRepository $lotUnitRepository
    ) {
    }

    public function getQuickStats(User $user): array
    {
        // Stats des 7 derniers jours
        $weekAgo = new \DateTime('-7 days');
        
        $weekSales = $this->lotUnitRepository->createQueryBuilder('lu')
            ->join('lu.lotGroup', 'lg')
            ->join('lg.dofusCharacter', 'c')
            ->join('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->andWhere('lu.soldAt >= :date')
            ->setParameter('user', $user)
            ->setParameter('date', $weekAgo)
            ->getQuery()
            ->getResult();

        $weekProfit = 0;
        foreach ($weekSales as $sale) {
            $weekProfit += $sale->getActualSellPrice() - $sale->getLotGroup()->getBuyPricePerLot();
        }

        // Meilleur item de la semaine
        $bestItem = null;
        $bestProfit = 0;
        $itemProfits = [];

        foreach ($weekSales as $sale) {
            $itemName = $sale->getLotGroup()->getItem()->getName();
            $profit = $sale->getActualSellPrice() - $sale->getLotGroup()->getBuyPricePerLot();
            
            if (!isset($itemProfits[$itemName])) {
                $itemProfits[$itemName] = 0;
            }
            $itemProfits[$itemName] += $profit;
            
            if ($itemProfits[$itemName] > $bestProfit) {
                $bestProfit = $itemProfits[$itemName];
                $bestItem = $itemName;
            }
        }

        return [
            'weekSalesCount' => count($weekSales),
            'weekProfit' => $weekProfit,
            'bestItem' => $bestItem,
            'bestItemProfit' => $bestProfit,
        ];
    }
}