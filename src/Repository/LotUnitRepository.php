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

    /**
     * DataTables server-side processing
     */
    public function findSalesForDataTable(
        User $user,
        ?DofusCharacter $character = null,
        ?string $period = null,
        array $dtParams = []
    ): array {
        $qb = $this->createQueryBuilder('lu')
            ->select([
                'lu.id',
                'lu.soldAt',
                'lu.quantitySold',
                'lu.actualSellPrice',
                'lu.notes',
                'lg.id as lotId',
                'lg.buyPricePerLot',
                'lg.sellPricePerLot',
                'i.name as itemName',
                'i.imgUrl as itemImage'
            ])
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

        // Recherche globale DataTables
        if (!empty($dtParams['search']['value'])) {
            $searchValue = $dtParams['search']['value'];
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('i.name', ':search'),
                    $qb->expr()->like('lu.notes', ':search')
                )
            )->setParameter('search', '%' . $searchValue . '%');
        }

        // Tri DataTables
        if (!empty($dtParams['order'])) {
            $orderColumnIndex = $dtParams['order'][0]['column'];
            $orderDirection = $dtParams['order'][0]['dir'];

            $columns = ['lu.soldAt', 'i.name', 'lu.quantitySold', 'lg.sellPricePerLot', 'lu.actualSellPrice', 'calculated_profit', 'calculated_performance'];

            if (isset($columns[$orderColumnIndex])) {
                $orderColumn = $columns[$orderColumnIndex];

                if ($orderColumn === 'calculated_profit') {
                    $qb->addSelect('((lu.actualSellPrice - lg.buyPricePerLot) * lu.quantitySold) as HIDDEN calculated_profit')
                       ->orderBy('calculated_profit', $orderDirection);
                } elseif ($orderColumn === 'calculated_performance') {
                    $qb->addSelect('(CASE WHEN lg.sellPricePerLot > 0 THEN (lu.actualSellPrice / lg.sellPricePerLot * 100) ELSE 0 END) as HIDDEN calculated_performance')
                       ->orderBy('calculated_performance', $orderDirection);
                } else {
                    $qb->orderBy($orderColumn, $orderDirection);
                }
            }
        } else {
            $qb->orderBy('lu.soldAt', 'DESC');
        }

        // Compter le total pour DataTables (avant pagination)
        $countQb = $this->createQueryBuilder('lu')
            ->select('COUNT(lu.id)')
            ->join('lu.lotGroup', 'lg')
            ->join('lg.dofusCharacter', 'c')
            ->join('lg.item', 'i')
            ->join('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->setParameter('user', $user);

        if ($character) {
            $countQb->andWhere('c = :character')
                    ->setParameter('character', $character);
        }

        if ($period && $period !== 'all') {
            $date = new \DateTime();
            $date->modify("-{$period} days");
            $countQb->andWhere('lu.soldAt >= :date')
                    ->setParameter('date', $date);
        }

        // Appliquer aussi la recherche pour le count filtré
        if (!empty($dtParams['search']['value'])) {
            $searchValue = $dtParams['search']['value'];
            $countQb->andWhere(
                $countQb->expr()->orX(
                    $countQb->expr()->like('i.name', ':search'),
                    $countQb->expr()->like('lu.notes', ':search')
                )
            )->setParameter('search', '%' . $searchValue . '%');
        }

        $totalRecords = $countQb->getQuery()->getSingleScalarResult();

        // Pagination DataTables
        if (isset($dtParams['start']) && isset($dtParams['length'])) {
            $qb->setFirstResult($dtParams['start'])
               ->setMaxResults($dtParams['length']);
        }

        // Assurer que nous avons des entiers et des résultats en array
        $totalRecords = (int) $totalRecords;
        $results = $qb->getQuery()->getArrayResult();

        return [
            'data' => $results,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalRecords,
        ];
    }
}