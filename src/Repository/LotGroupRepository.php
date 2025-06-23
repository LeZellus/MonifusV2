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

    // ===== AJOUTEZ CES MÉTHODES À LA FIN DE VOTRE LotGroupRepository =====
    
    public function findByCharacterOptimized(DofusCharacter $character): array
    {
        return $this->createQueryBuilder('lg')
            ->select('lg', 'i', 'c') // Précharge les relations
            ->leftJoin('lg.item', 'i')
            ->leftJoin('lg.dofusCharacter', 'c')
            ->where('lg.dofusCharacter = :character')
            ->setParameter('character', $character)
            ->orderBy('lg.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Analytics en une seule requête (version compatible)
     */
    public function getCharacterAnalytics(DofusCharacter $character): array
    {
        $result = $this->createQueryBuilder('lg')
            ->select([
                'COUNT(lg.id) as totalLots',
                'SUM(lg.buyPricePerLot * lg.lotSize) as totalInvestment'
            ])
            ->where('lg.dofusCharacter = :character')
            ->setParameter('character', $character)  // ← Cette ligne MANQUE !
            ->getQuery()
            ->getSingleResult();
            
        return [
            'totalLots' => (int)($result['totalLots'] ?? 0),
            'totalInvestment' => (int)($result['totalInvestment'] ?? 0)
        ];
    }

    /**
     * Analytics complémentaires (version corrigée)
     */
    public function getCharacterAnalyticsDetailed(DofusCharacter $character): array
    {
        // Stats de base
        $baseStats = $this->getCharacterAnalytics($character);
        
        // Expected revenue (lots avec prix de vente)
        $expectedRevenue = (int)($this->createQueryBuilder('lg')
            ->select('SUM(lg.sellPricePerLot * lg.lotSize)')
            ->where('lg.dofusCharacter = :character')
            ->andWhere('lg.sellPricePerLot IS NOT NULL')
            ->setParameter('character', $character)
            ->getQuery()
            ->getSingleScalarResult() ?? 0);
        
        // Expected profit
        $expectedProfit = (int)($this->createQueryBuilder('lg')
            ->select('SUM((lg.sellPricePerLot - lg.buyPricePerLot) * lg.lotSize)')
            ->where('lg.dofusCharacter = :character')
            ->andWhere('lg.sellPricePerLot IS NOT NULL')
            ->setParameter('character', $character)
            ->getQuery()
            ->getSingleScalarResult() ?? 0);
        
        // Active lots
        $activeLots = (int)($this->createQueryBuilder('lg')
            ->select('COUNT(lg.id)')
            ->where('lg.dofusCharacter = :character')
            ->andWhere('lg.status = :available')
            ->setParameter('character', $character)
            ->setParameter('available', LotStatus::AVAILABLE)
            ->getQuery()
            ->getSingleScalarResult() ?? 0);
        
        return array_merge($baseStats, [
            'expectedRevenue' => $expectedRevenue,
            'expectedProfit' => $expectedProfit,
            'activeLots' => $activeLots
        ]);
    }

    /**
     * Test pour comparer les performances
     */
    public function benchmarkMethods(DofusCharacter $character): array
    {
        $results = [];
        
        try {
            // Test méthode ancienne vs nouvelle
            $startTime = microtime(true);
            $oldResults = $this->findByCharacterOrderedByDate($character);
            $oldTime = (microtime(true) - $startTime) * 1000;
            
            $startTime = microtime(true);
            $newResults = $this->findByCharacterOptimized($character);
            $newTime = (microtime(true) - $startTime) * 1000;
            
            $results['findByCharacter'] = [
                'old_method_ms' => round($oldTime, 2),
                'new_method_ms' => round($newTime, 2),
                'improvement_percent' => $oldTime > 0 ? round((($oldTime - $newTime) / $oldTime) * 100, 1) : 0,
                'same_results' => count($oldResults) === count($newResults)
            ];
        } catch (\Exception $e) {
            $results['findByCharacter'] = [
                'error' => $e->getMessage(),
                'old_method_ms' => 0,
                'new_method_ms' => 0,
                'improvement_percent' => 0
            ];
        }
        
        return $results;
    }
}