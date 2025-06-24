<?php

namespace App\Service;

class ChartDataService
{
    private const MAX_CHART_POINTS = 10;
    
    /**
     * Prépare les données pour Chart.js à partir de l'historique des prix
     */
    public function prepareMarketWatchChartData(array $priceHistory): array
    {
        if (empty($priceHistory)) {
            return $this->getEmptyChartData();
        }

        // Tri par date croissante pour le graphique
        usort($priceHistory, fn($a, $b) => $a->getObservedAt() <=> $b->getObservedAt());
        
        // Réduire les points si nécessaire
        $priceHistory = $this->reduceDataPoints($priceHistory);
        
        // Extraction des données
        $chartData = $this->extractPriceData($priceHistory);
        
        return [
            'labels' => $chartData['labels'],
            'datasets' => $this->buildDatasets($chartData)
        ];
    }

    /**
     * Réduit le nombre de points si trop nombreux
     */
    private function reduceDataPoints(array $priceHistory): array
    {
        $totalObservations = count($priceHistory);
        
        if ($totalObservations <= self::MAX_CHART_POINTS) {
            return $priceHistory;
        }

        $step = max(1, intval($totalObservations / self::MAX_CHART_POINTS));
        return array_filter($priceHistory, fn($key) => $key % $step === 0, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Extrait toutes les données de prix et calcule les moyennes mobiles
     */
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
            // Format de date adaptatif
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

    /**
     * Construit les datasets pour Chart.js
     */
    private function buildDatasets(array $chartData): array
    {
        $datasets = [];
        
        // Configuration des couleurs
        $colors = [
            'x1' => ['border' => '#10B981', 'background' => 'rgba(16, 185, 129, 0.1)'],
            'x10' => ['border' => '#3B82F6', 'background' => 'rgba(59, 130, 246, 0.1)'],
            'x100' => ['border' => '#8B5CF6', 'background' => 'rgba(139, 92, 246, 0.1)']
        ];

        // Prix observés
        if ($chartData['countX1'] > 0) {
            $datasets[] = $this->createDataset('Prix x1 observé', $chartData['priceDataX1'], $colors['x1']);
        }
        
        if ($chartData['countX10'] > 0) {
            $datasets[] = $this->createDataset('Prix x10 observé', $chartData['priceDataX10'], $colors['x10']);
        }
        
        if ($chartData['countX100'] > 0) {
            $datasets[] = $this->createDataset('Prix x100 observé', $chartData['priceDataX100'], $colors['x100']);
        }

        // Moyennes mobiles (en pointillés)
        if ($chartData['countX1'] > 0) {
            $datasets[] = $this->createDataset('Moyenne mobile x1', $chartData['averageDataX1'], $colors['x1'], true);
        }
        
        if ($chartData['countX10'] > 0) {
            $datasets[] = $this->createDataset('Moyenne mobile x10', $chartData['averageDataX10'], $colors['x10'], true);
        }
        
        if ($chartData['countX100'] > 0) {
            $datasets[] = $this->createDataset('Moyenne mobile x100', $chartData['averageDataX100'], $colors['x100'], true);
        }

        return $datasets;
    }

    /**
     * Crée un dataset individuel
     */
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

    /**
     * Formate le label de date selon le nombre total d'observations
     */
    private function formatDateLabel(array $priceHistory, $observation): string
    {
        return count($priceHistory) > 7 
            ? $observation->getObservedAt()->format('d/m')
            : $observation->getObservedAt()->format('d/m/y');
    }

    /**
     * Retourne une structure vide pour les cas sans données
     */
    private function getEmptyChartData(): array
    {
        return [
            'labels' => [],
            'datasets' => []
        ];
    }
}