<?php

namespace App\Service;

use DateTime;

class ChartDataService
{
    private const MAX_CHART_POINTS = 50; // Augmenté pour les périodes courtes
    
    /**
     * Prépare les données avec filtrage temporel
     */
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

    /**
     * Filtre les observations selon la période
     */
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

    /**
     * Réduit intelligemment le nombre de points
     */
    private function reduceDataPoints(array $priceHistory): array
    {
        $totalObservations = count($priceHistory);
        
        if ($totalObservations <= self::MAX_CHART_POINTS) {
            return $priceHistory;
        }

        // Algorithme amélioré : garde toujours le premier et le dernier
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

    // ... reste des méthodes inchangées
    private function extractPriceData(array $priceHistory): array
    {
        $labels = [];
        $priceDataX1 = [];
        $priceDataX10 = [];
        $priceDataX100 = [];
        $averageDataX1 = [];
        $averageDataX10 = [];
        $averageDataX100 = [];
        
        $runningSumX1 = 0;
        $runningSumX10 = 0;
        $runningSumX100 = 0;
        $countX1 = 0;
        $countX10 = 0;
        $countX100 = 0;
        
        foreach ($priceHistory as $observation) {
            $labels[] = $this->formatDateLabel($priceHistory, $observation);
            
            // Prix x1
            $priceX1 = $observation->getPricePerUnit();
            $priceDataX1[] = $priceX1 ? intval($priceX1) : null;
            if ($priceX1) {
                $runningSumX1 += $priceX1;
                $countX1++;
            }
            $averageDataX1[] = $countX1 > 0 ? intval(round($runningSumX1 / $countX1)) : null;
            
            // Prix x10
            $priceX10 = $observation->getPricePer10();
            $priceDataX10[] = $priceX10 ? intval($priceX10) : null;
            if ($priceX10) {
                $runningSumX10 += $priceX10;
                $countX10++;
            }
            $averageDataX10[] = $countX10 > 0 ? intval(round($runningSumX10 / $countX10)) : null;
            
            // Prix x100
            $priceX100 = $observation->getPricePer100();
            $priceDataX100[] = $priceX100 ? intval($priceX100) : null;
            if ($priceX100) {
                $runningSumX100 += $priceX100;
                $countX100++;
            }
            $averageDataX100[] = $countX100 > 0 ? intval(round($runningSumX100 / $countX100)) : null;
        }

        return [
            'labels' => $labels,
            'priceDataX1' => $priceDataX1,
            'priceDataX10' => $priceDataX10,
            'priceDataX100' => $priceDataX100,
            'averageDataX1' => $averageDataX1,
            'averageDataX10' => $averageDataX10,
            'averageDataX100' => $averageDataX100,
            'countX1' => $countX1,
            'countX10' => $countX10,
            'countX100' => $countX100
        ];
    }

    private function buildDatasets(array $chartData): array
    {
        $datasets = [];
        
        $colors = [
            'x1' => ['border' => '#10B981', 'background' => 'rgba(16, 185, 129, 0.1)'],
            'x10' => ['border' => '#3B82F6', 'background' => 'rgba(59, 130, 246, 0.1)'],
            'x100' => ['border' => '#8B5CF6', 'background' => 'rgba(139, 92, 246, 0.1)']
        ];

        // Prix observés + moyennes
        foreach (['x1', 'x10', 'x100'] as $type) {
            $countKey = "count" . strtoupper($type);
            if ($chartData[$countKey] > 0) {
                $priceKey = "priceData" . strtoupper($type);
                $avgKey = "averageData" . strtoupper($type);
                
                $datasets[] = $this->createDataset("Prix $type observé", $chartData[$priceKey], $colors[$type]);
                $datasets[] = $this->createDataset("Moyenne mobile $type", $chartData[$avgKey], $colors[$type], true);
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