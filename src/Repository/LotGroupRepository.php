<?php

namespace App\Repository;

use App\Entity\LotGroup;
use App\Entity\DofusCharacter;
use App\Entity\User;
use App\Enum\LotStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LotGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LotGroup::class);
    }

    // ===== MÉTHODES PRINCIPALES (OPTIMISÉES) =====

    /**
     * ✅ REMPLACE findByCharacterOrderedByDate ET findByCharacterWithItems
     * Une seule méthode optimisée qui précharge tout
     */
    public function findByCharacterOptimized(DofusCharacter $character): array
    {
        return $this->createQueryBuilder('lg')
            ->select('lg', 'i', 'c')
            ->leftJoin('lg.item', 'i')
            ->leftJoin('lg.dofusCharacter', 'c')
            ->where('lg.dofusCharacter = :character')
            ->setParameter('character', $character)
            ->orderBy('lg.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * ✅ REMPLACE getUserGlobalStats avec une approche plus simple
     * Une seule requête avec GROUP BY au lieu de multiples requêtes
     */
    public function getUserGlobalStats(User $user): array
    {
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

        // Parser les résultats
        $stats = [
            'totalLots' => 0,
            'availableLots' => 0,
            'soldLots' => 0,
            'investedAmount' => 0,
            'realizedProfit' => 0,
            'potentialProfit' => 0
        ];

        foreach ($result as $row) {
            $status = $row['status']->value;
            $count = (int)$row['totalLots'];
            $investment = (int)$row['totalInvestment'];
            
            $stats['totalLots'] += $count;
            
            if ($status === 'available') {
                $stats['availableLots'] = $count;
                $stats['investedAmount'] = $investment;
            } elseif ($status === 'sold') {
                $stats['soldLots'] = $count;
            }
        }

        return $stats;
    }

    /**
     * ✅ REMPLACE getGlobalStatistics (version simplifiée pour HomeController)
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
            'total_potential_profit' => 0, // À calculer si besoin
            'total_lots_managed' => (int)($result['totalLotsManaged'] ?? 0)
        ];
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

    // ===== MÉTHODES UTILITAIRES =====

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
}