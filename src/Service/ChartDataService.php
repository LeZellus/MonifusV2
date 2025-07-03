<?php

namespace App\Service;

use DateTime;

class ChartDataService
{
    private const MAX_CHART_POINTS = 50;
    
    // Configuration des types de prix
    private const PRICE_TYPES = [
        'x1' => ['method' => 'getPricePerUnit', 'color' => '#10B981'],
        'x10' => ['method' => 'getPricePer10', 'color' => '#3B82F6'],
        'x100' => ['method' => 'getPricePer100', 'color' => '#8B5CF6'],
        'x1000' => ['method' => 'getPricePer1000', 'color' => '#F59E0B']
    ];
    
    public function prepareMarketWatchChartData(array $priceHistory, ?string $period = null): array
    {
        if (empty($priceHistory)) {
            return $this->getEmptyChartData();
        }

        // Tri par date croissante
        usort($priceHistory, fn($a, $b) => $a->getObservedAt() <=> $b->getObservedAt());
        
        // Filtrage temporel
        $filteredHistory = $this->filterByPeriod($priceHistory, $period);
        
        // Réduction des points si nécessaire
        $filteredHistory = $this->reduceDataPoints($filteredHistory);
        
        $chartData = $this->extractPriceData($filteredHistory);
        
        return [
            'labels' => $chartData['labels'],
            'datasets' => $this->buildDatasets($chartData),
            'period' => $period,
            'totalPoints' => count($priceHistory),
            'displayedPoints' => count($filteredHistory)
        ];
    }

    private function filterByPeriod(array $priceHistory, ?string $period): array
    {
        if (!$period || $period === 'all') {
            return $priceHistory;
        }

        $now = new DateTime();
        $cutoffDate = clone $now;

        switch ($period) {
            case '7d':
                $cutoffDate->modify('-7 days');
                break;
            case '30d':
                $cutoffDate->modify('-30 days');
                break;
            case '3m':
                $cutoffDate->modify('-3 months');
                break;
            case '6m':
                $cutoffDate->modify('-6 months');
                break;
            case '1y':
                $cutoffDate->modify('-1 year');
                break;
            default:
                return $priceHistory;
        }

        return array_filter($priceHistory, function($observation) use ($cutoffDate) {
            return $observation->getObservedAt() >= $cutoffDate;
        });
    }

    private function reduceDataPoints(array $priceHistory): array
    {
        $totalObservations = count($priceHistory);
        
        if ($totalObservations <= self::MAX_CHART_POINTS) {
            return $priceHistory;
        }

        $step = max(1, intval($totalObservations / (self::MAX_CHART_POINTS - 2)));
        $reducedHistory = [];
        
        // Premier point
        $reducedHistory[] = $priceHistory[0];
        
        // Points intermédiaires
        for ($i = $step; $i < $totalObservations - 1; $i += $step) {
            $reducedHistory[] = $priceHistory[$i];
        }
        
        // Dernier point (si différent du premier)
        if ($totalObservations > 1) {
            $reducedHistory[] = $priceHistory[$totalObservations - 1];
        }
        
        return $reducedHistory;
    }

    // ✅ VERSION OPTIMISÉE : Élimine toute la répétition
    private function extractPriceData(array $priceHistory): array
    {
        $labels = [];
        $data = [];
        
        // Initialisation des données pour chaque type
        foreach (self::PRICE_TYPES as $type => $config) {
            $data[$type] = [
                'prices' => [],
                'averages' => [],
                'runningSum' => 0,
                'count' => 0
            ];
        }
        
        foreach ($priceHistory as $observation) {
            $labels[] = $this->formatDateLabel($priceHistory, $observation);
            
            // Traitement pour chaque type de prix
            foreach (self::PRICE_TYPES as $type => $config) {
                $method = $config['method'];
                $price = $observation->$method();
                
                // Ajouter le prix (ou null)
                $data[$type]['prices'][] = $price ? intval($price) : null;
                
                // Calculer la moyenne mobile
                if ($price) {
                    $data[$type]['runningSum'] += $price;
                    $data[$type]['count']++;
                }
                
                $average = $data[$type]['count'] > 0 
                    ? intval(round($data[$type]['runningSum'] / $data[$type]['count'])) 
                    : null;
                    
                $data[$type]['averages'][] = $average;
            }
        }

        // Construire le tableau de retour
        $result = ['labels' => $labels];
        
        foreach (self::PRICE_TYPES as $type => $config) {
            $typeUpper = strtoupper($type);
            $result["priceData{$typeUpper}"] = $data[$type]['prices'];
            $result["averageData{$typeUpper}"] = $data[$type]['averages'];
            $result["count{$typeUpper}"] = $data[$type]['count'];
        }
        
        return $result;
    }

    // ✅ VERSION OPTIMISÉE : Génération dynamique des datasets
    private function buildDatasets(array $chartData): array
    {
        $datasets = [];
        
        foreach (self::PRICE_TYPES as $type => $config) {
            $countKey = "count" . strtoupper($type);
            
            if ($chartData[$countKey] > 0) {
                $priceKey = "priceData" . strtoupper($type);
                $avgKey = "averageData" . strtoupper($type);
                
                $colors = [
                    'border' => $config['color'],
                    'background' => $this->hexToRgba($config['color'], 0.1)
                ];
                
                // Prix observé
                $datasets[] = $this->createDataset(
                    "Prix $type observé", 
                    $chartData[$priceKey], 
                    $colors
                );
                
                // Moyenne mobile
                $datasets[] = $this->createDataset(
                    "Moyenne mobile $type", 
                    $chartData[$avgKey], 
                    $colors, 
                    true
                );
            }
        }

        return $datasets;
    }

    private function createDataset(string $label, array $data, array $colors, bool $isDashed = false): array
    {
        $dataset = [
            'label' => $label,
            'data' => $data,
            'borderColor' => $colors['border'],
            'backgroundColor' => $isDashed ? 'transparent' : $colors['background'],
            'fill' => false,
            'tension' => 0.1,
            'spanGaps' => true
        ];

        if ($isDashed) {
            $dataset['borderDash'] = [5, 5];
        }

        return $dataset;
    }

    // ✅ HELPER : Convertit une couleur hex en rgba
    private function hexToRgba(string $hex, float $alpha): string
    {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        return "rgba($r, $g, $b, $alpha)";
    }

    private function formatDateLabel(array $priceHistory, $observation): string
    {
        return count($priceHistory) > 7 
            ? $observation->getObservedAt()->format('d/m')
            : $observation->getObservedAt()->format('d/m/y');
    }

    private function getEmptyChartData(): array
    {
        return [
            'labels' => [],
            'datasets' => [],
            'period' => null,
            'totalPoints' => 0,
            'displayedPoints' => 0
        ];
    }
}