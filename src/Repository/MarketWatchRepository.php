<?php

namespace App\Repository;

use App\Entity\MarketWatch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\DofusCharacter;

/**
 * @extends ServiceEntityRepository<MarketWatch>
 */
class MarketWatchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MarketWatch::class);
    }

    /**
     * Récupère toutes les observations pour un personnage
     */
    public function findByCharacterWithItems(DofusCharacter $character, string $searchQuery = ''): array
    {
        $qb = $this->createQueryBuilder('mw')
            ->leftJoin('mw.item', 'i')
            ->leftJoin('mw.dofusCharacter', 'c')
            ->addSelect('i', 'c')  // Ajout des select pour éviter les requêtes N+1
            ->where('mw.dofusCharacter = :character')
            ->setParameter('character', $character)
            ->orderBy('mw.observedAt', 'DESC'); // Ordre par défaut : plus récent en premier

        // Ajout de la recherche si un terme est fourni
        if (!empty($searchQuery)) {
            $qb->andWhere('LOWER(i.name) LIKE LOWER(:search)')
            ->setParameter('search', '%' . $searchQuery . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Récupère l'historique des prix pour un item spécifique
     */
    public function findPriceHistoryForItem(DofusCharacter $character, int $itemId): array
    {
        return $this->createQueryBuilder('mw')
            ->where('mw.dofusCharacter = :character')
            ->andWhere('mw.item = :itemId')
            ->setParameter('character', $character)
            ->setParameter('itemId', $itemId)
            ->orderBy('mw.observedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule les moyennes de prix à partir d'observations
     */
    public function calculatePriceAverages(array $observations): array
    {
        $pricesUnit = [];
        $prices10 = [];
        $prices100 = [];
        $prices1000 = [];
        
        foreach ($observations as $obs) {
            if ($obs->getPricePerUnit() !== null) {
                $pricesUnit[] = $obs->getPricePerUnit();
            }
            if ($obs->getPricePer10() !== null) {
                $prices10[] = $obs->getPricePer10();
            }
            if ($obs->getPricePer100() !== null) {
                $prices100[] = $obs->getPricePer100();
            }
            if ($obs->getPricePer1000() !== null) {
                $prices1000[] = $obs->getPricePer1000();
            }
        }
        
        return [
            'avg_price_unit' => !empty($pricesUnit) ? round(array_sum($pricesUnit) / count($pricesUnit)) : null,
            'avg_price_10' => !empty($prices10) ? round(array_sum($prices10) / count($prices10)) : null,
            'avg_price_100' => !empty($prices100) ? round(array_sum($prices100) / count($prices100)) : null,
            'avg_price_1000' => !empty($prices1000) ? round(array_sum($prices1000) / count($prices1000)) : null,
            'price_unit_count' => count($pricesUnit),
            'price_10_count' => count($prices10),
            'price_100_count' => count($prices100),
            'price_1000_count' => count($prices1000)
        ];
    }

    /**
     * Groupe les observations par item et calcule les statistiques
     */
    public function getItemsDataWithStats(DofusCharacter $character, string $searchQuery = ''): array
    {
        // 1. Votre méthode actuelle utilise findByCharacterWithItems() 
        // 2. On va juste ajouter le filtre de recherche dans cette logique
        
        $observations = $this->findByCharacterWithItems($character, $searchQuery);
        
        // Le reste de votre code existant ne change pas du tout !
        // Grouper par item
        $itemsGrouped = [];
        foreach ($observations as $observation) {
            $itemId = $observation->getItem()->getId();
            
            if (!isset($itemsGrouped[$itemId])) {
                $itemsGrouped[$itemId] = [
                    'item' => $observation->getItem(),
                    'observations' => [],
                    'latest_date' => $observation->getObservedAt(),
                    'oldest_date' => $observation->getObservedAt()
                ];
            }
            
            $itemsGrouped[$itemId]['observations'][] = $observation;
            
            // Mettre à jour les dates
            if ($observation->getObservedAt() > $itemsGrouped[$itemId]['latest_date']) {
                $itemsGrouped[$itemId]['latest_date'] = $observation->getObservedAt();
            }
            if ($observation->getObservedAt() < $itemsGrouped[$itemId]['oldest_date']) {
                $itemsGrouped[$itemId]['oldest_date'] = $observation->getObservedAt();
            }
        }

        // Calculer les statistiques pour chaque item
        $itemsData = [];
        foreach ($itemsGrouped as $itemId => $data) {
            $averages = $this->calculatePriceAverages($data['observations']);
            $trackingPeriod = $data['latest_date']->diff($data['oldest_date'])->days;
            
            $itemsData[] = [
                'item' => $data['item'],
                'observation_count' => count($data['observations']),
                'latest_date' => $data['latest_date'],
                'tracking_period_days' => $trackingPeriod,
                ...$averages
            ];
        }

        // Trier par date de dernière observation (plus récent en premier)
        usort($itemsData, function($a, $b) {
            return $b['latest_date'] <=> $a['latest_date'];
        });

        return $itemsData;
    }
}
