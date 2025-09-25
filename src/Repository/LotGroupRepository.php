<?php

namespace App\Repository;

use App\Entity\LotGroup;
use App\Entity\User;
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

    /**
     * Stats utilisateur avec différenciation investi en cours vs total
     */
    public function getUserGlobalStats(User $user): array
    {
        // Stats groupées par statut
        $result = $this->createQueryBuilder('lg')
            ->select([
                'COUNT(lg.id) as totalLots',
                'SUM(lg.buyPricePerLot * lg.lotSize) as totalInvestment',
                'lg.status'
            ])
            ->leftJoin('lg.dofusCharacter', 'c')
            ->leftJoin('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->groupBy('lg.status')
            ->setParameter('user', $user)
            ->getQuery()
            ->getArrayResult();

        // Profit potentiel (lots disponibles uniquement)
        $potentialProfitResult = $this->createQueryBuilder('lg')
            ->select('SUM((lg.sellPricePerLot - lg.buyPricePerLot) * lg.lotSize) as potentialProfit')
            ->leftJoin('lg.dofusCharacter', 'c')
            ->leftJoin('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->andWhere('lg.status = :available')
            ->setParameter('user', $user)
            ->setParameter('available', LotStatus::AVAILABLE)
            ->getQuery()
            ->getSingleScalarResult();

        // Profit réalisé (via LotUnit)
        $realizedProfitResult = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('SUM((lu.actualSellPrice - lg.buyPricePerLot) * lu.quantitySold) as realizedProfit')
            ->from('App\Entity\LotUnit', 'lu')
            ->join('lu.lotGroup', 'lg')
            ->leftJoin('lg.dofusCharacter', 'c')
            ->leftJoin('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        // NOUVEAU : Investi total historique (lots vendus)
        $soldInvestmentResult = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('SUM(lg.buyPricePerLot * lu.quantitySold) as soldInvestment')
            ->from('App\Entity\LotUnit', 'lu')
            ->join('lu.lotGroup', 'lg')
            ->leftJoin('lg.dofusCharacter', 'c')
            ->leftJoin('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        // Parser les résultats
        $stats = [
            'totalLots' => 0,
            'availableLots' => 0,
            'soldLots' => 0,
            'currentInvestment' => 0,        // NOUVEAU : Investi en cours
            'totalInvestment' => 0,          // NOUVEAU : Investi total historique
            'realizedProfit' => (int) ($realizedProfitResult ?? 0),
            'potentialProfit' => (int) ($potentialProfitResult ?? 0)
        ];

        foreach ($result as $row) {
            $status = $row['status']->value;
            $count = (int)$row['totalLots'];
            $investment = (int)$row['totalInvestment'];
            
            $stats['totalLots'] += $count;
            
            if ($status === 'available') {
                $stats['availableLots'] = $count;
                $stats['currentInvestment'] = $investment;
            } elseif ($status === 'sold') {
                $stats['soldLots'] = $count;
            }
        }

        // Calcul investi total = en cours + vendu
        $stats['totalInvestment'] = $stats['currentInvestment'] + (int)($soldInvestmentResult ?? 0);

        // LEGACY : pour compatibilité
        $stats['investedAmount'] = $stats['currentInvestment'];

        return $stats;
    }

    /**
     * Stats globales site (home page)
     */
    public function getGlobalStatistics(): array
    {
        $result = $this->createQueryBuilder('lg')
            ->select([
                'SUM(lg.buyPricePerLot * lg.lotSize) as totalInvested',
                'SUM(lg.lotSize) as totalLotsManaged'
            ])
            ->where('lg.status = :available')
            ->setParameter('available', LotStatus::AVAILABLE)
            ->getQuery()
            ->getSingleResult();

        return [
            'total_invested' => (int)($result['totalInvested'] ?? 0),
            'total_potential_profit' => 0,
            'total_lots_managed' => (int)($result['totalLotsManaged'] ?? 0)
        ];
    }

    /**
     * Lots disponibles pour un personnage avec eager loading des relations Item
     */
    public function findAvailableByCharacter(DofusCharacter $character): array
    {
        return $this->createQueryBuilder('lg')
            ->leftJoin('lg.item', 'i')
            ->addSelect('i')
            ->where('lg.dofusCharacter = :character')
            ->andWhere('lg.status = :available')
            ->setParameter('character', $character)
            ->setParameter('available', LotStatus::AVAILABLE)
            ->orderBy('lg.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche sécurisée par ID et personnage
     */
    public function findOneByIdAndCharacter(int $id, DofusCharacter $character): ?LotGroup
    {
        return $this->createQueryBuilder('lg')
            ->where('lg.id = :id AND lg.dofusCharacter = :character')
            ->setParameter('id', $id)
            ->setParameter('character', $character)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Analytics simples pour un personnage
     */
    public function getCharacterAnalytics(DofusCharacter $character): array
    {
        $result = $this->createQueryBuilder('lg')
            ->select([
                'COUNT(lg.id) as totalLots',
                'SUM(lg.buyPricePerLot * lg.lotSize) as totalInvestment'
            ])
            ->where('lg.dofusCharacter = :character')
            ->setParameter('character', $character)
            ->getQuery()
            ->getSingleResult();
            
        return [
            'totalLots' => (int)($result['totalLots'] ?? 0),
            'totalInvestment' => (int)($result['totalInvestment'] ?? 0)
        ];
    }

    /**
     * Top items par profit pour un utilisateur
     */
    public function getTopItemsByProfit(User $user, int $limit = 5): array
    {
        return $this->createQueryBuilder('lg')
            ->select([
                'i.name as itemName',
                'COUNT(lg.id) as lotsCount',
                'SUM((lg.sellPricePerLot - lg.buyPricePerLot) * lg.lotSize) as totalProfit',
                'AVG((lg.sellPricePerLot - lg.buyPricePerLot) / lg.buyPricePerLot * 100) as roi',
                'SUM(lg.buyPricePerLot * lg.lotSize) as totalInvested'
            ])
            ->join('lg.item', 'i')
            ->join('lg.dofusCharacter', 'c')
            ->join('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->setParameter('user', $user)
            ->groupBy('i.id', 'i.name')
            ->orderBy('totalProfit', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function searchByItemName(DofusCharacter $character, string $searchQuery = ''): array
    {
        $qb = $this->createQueryBuilder('lg')
            ->leftJoin('lg.item', 'i')
            ->addSelect('i')
            ->leftJoin('lg.dofusCharacter', 'c')
            ->where('c = :character')
            ->setParameter('character', $character)
            ->orderBy('lg.createdAt', 'DESC');

        // Si une recherche est spécifiée, filtrer par nom d'item
        if (!empty($searchQuery)) {
            $qb->andWhere('LOWER(i.name) LIKE LOWER(:search)')
            ->setParameter('search', '%' . $searchQuery . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Récupération paginée et triée des lots pour datatable
     */
    public function findPaginatedAndSorted(
        DofusCharacter $character,
        string $search = '',
        int $page = 1,
        int $length = 25,
        int $sortColumn = 0,
        string $sortDirection = 'desc'
    ): array {
        $qb = $this->createQueryBuilder('lg')
            ->leftJoin('lg.item', 'i')
            ->addSelect('i')
            ->where('lg.dofusCharacter = :character')
            ->setParameter('character', $character);

        // Recherche par nom d'item
        if (!empty($search)) {
            $qb->andWhere('LOWER(i.name) LIKE LOWER(:search)')
               ->setParameter('search', '%' . $search . '%');
        }

        // Tri SQL
        $columns = ['i.name', 'lg.lotSize', 'lg.buyPricePerLot', 'lg.sellPricePerLot', 'lg.sellPricePerLot - lg.buyPricePerLot', 'lg.status'];
        if (isset($columns[$sortColumn])) {
            $orderBy = $columns[$sortColumn];
            // Pour le tri par profit, nous devons utiliser une expression
            if ($sortColumn === 4) {
                $qb->addSelect('(lg.sellPricePerLot - lg.buyPricePerLot) as HIDDEN profit');
                $orderBy = 'profit';
            }
            $qb->orderBy($orderBy, $sortDirection);
        } else {
            $qb->orderBy('lg.createdAt', 'DESC');
        }

        // Compter le total avant pagination
        $countQuery = clone $qb;
        $countQuery->select('COUNT(lg.id)');
        $totalRecords = (int) $countQuery->getQuery()->getSingleScalarResult();

        // Pagination
        $offset = ($page - 1) * $length;
        $qb->setFirstResult($offset)
           ->setMaxResults($length);

        $lots = $qb->getQuery()->getResult();

        return [
            'lots' => $lots,
            'totalRecords' => $totalRecords
        ];
    }

    /**
     * Stats complètes pour un personnage spécifique
     */
    public function getCharacterCompleteStats(DofusCharacter $character): array
    {
        // Stats groupées par statut
        $result = $this->createQueryBuilder('lg')
            ->select([
                'COUNT(lg.id) as totalLots',
                'SUM(lg.buyPricePerLot * lg.lotSize) as totalInvestment',
                'lg.status'
            ])
            ->where('lg.dofusCharacter = :character')
            ->groupBy('lg.status')
            ->setParameter('character', $character)
            ->getQuery()
            ->getArrayResult();

        $baseStats = [
            'totalLots' => 0,
            'activeLots' => 0,
            'soldLots' => 0,
            'totalInvestment' => 0,
            'currentInvestment' => 0,
        ];

        foreach ($result as $stat) {
            $status = $stat['status']->value; // Enum vers string
            $baseStats['totalLots'] += (int) $stat['totalLots'];
            $baseStats['totalInvestment'] += (int) ($stat['totalInvestment'] ?? 0);

            if ($status === 'available') {
                $baseStats['activeLots'] = (int) $stat['totalLots'];
                $baseStats['currentInvestment'] = (int) ($stat['totalInvestment'] ?? 0);
            }
            // Note: soldLots sera recalculé plus bas basé sur les vraies ventes
        }

        // Compter les lots vendus par statut (pour cohérence avec l'interface utilisateur)
        $soldLotsFromStatus = 0;
        foreach ($result as $stat) {
            if ($stat['status']->value === 'sold') {
                $soldLotsFromStatus = (int) $stat['totalLots'];
                break;
            }
        }

        $baseStats['soldLots'] = $soldLotsFromStatus;

        // Profit potentiel (lots disponibles uniquement)
        $potentialProfitResult = $this->createQueryBuilder('lg')
            ->select('SUM((lg.sellPricePerLot - lg.buyPricePerLot) * lg.lotSize) as potentialProfit')
            ->where('lg.dofusCharacter = :character')
            ->andWhere('lg.status = :available')
            ->setParameter('character', $character)
            ->setParameter('available', LotStatus::AVAILABLE)
            ->getQuery()
            ->getSingleScalarResult();

        // Profit réalisé (sur les lots vendus via LotUnit)
        $realizedProfitResult = $this->createQueryBuilder('lg')
            ->select('SUM((lu.actualSellPrice - lg.buyPricePerLot) * lu.quantitySold) as realizedProfit')
            ->leftJoin('lg.lotUnits', 'lu')
            ->where('lg.dofusCharacter = :character')
            ->andWhere('lg.status = :sold')
            ->setParameter('character', $character)
            ->setParameter('sold', LotStatus::SOLD)
            ->getQuery()
            ->getSingleScalarResult();

        // Transactions réalisées
        $transactionsResult = $this->createQueryBuilder('lg')
            ->select('COUNT(lu.id) as transactions')
            ->leftJoin('lg.lotUnits', 'lu')
            ->where('lg.dofusCharacter = :character')
            ->andWhere('lg.status = :sold')
            ->setParameter('character', $character)
            ->setParameter('sold', LotStatus::SOLD)
            ->getQuery()
            ->getSingleScalarResult();

        return array_merge($baseStats, [
            'potentialProfit' => (int) ($potentialProfitResult ?? 0),
            'realizedProfit' => (int) ($realizedProfitResult ?? 0),
            'totalTransactions' => (int) ($transactionsResult ?? 0),
        ]);
    }
}