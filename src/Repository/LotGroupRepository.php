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
     * Lots disponibles pour un personnage
     */
    public function findAvailableByCharacter(DofusCharacter $character): array
    {
        return $this->createQueryBuilder('lg')
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
}