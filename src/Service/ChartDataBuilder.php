<?php

namespace App\Service;

use DateTime;

/**
 * Builder simplifié pour les données de graphiques
 * Version allégée du ChartDataService original
 */
class ChartDataBuilder
{
    private const MAX_CHART_POINTS = 50;

    private const PRICE_TYPES = [
        'x1' => ['method' => 'getPricePerUnit', 'color' => '#10B981'],
        'x10' => ['method' => 'getPricePer10', 'color' => '#3B82F6'],
        'x100' => ['method' => 'getPricePer100', 'color' => '#8B5CF6'],
        'x1000' => ['method' => 'getPricePer1000', 'color' => '#F59E0B']
    ];

    public function buildMarketWatchChart(array $priceHistory, ?string $period = null): array
    {
        if (empty($priceHistory)) {
            return $this->getEmptyChart();
        }

        $processedData = $this->processData($priceHistory, $period);

        return [
            'labels' => $processedData['labels'],
            'datasets' => $this->createDatasets($processedData['data']),
            'meta' => [
                'period' => $period ?? 'all',
                'totalPoints' => count($priceHistory),
                'displayedPoints' => count($processedData['data'])
            ]
        ];
    }

    public function buildPerformanceChart(array $performanceData): array
    {
        $labels = array_column($performanceData, 'date');
        $profits = array_column($performanceData, 'profit');

        return [
            'labels' => $labels,
            'datasets' => [{
                'label' => 'Profits',
                'data' => $profits,
                'borderColor' => '#10B981',
                'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                'fill' => true
            }],
            'meta' => [
                'type' => 'performance',
                'dataPoints' => count($performanceData)
            ]
        ];
    }

    private function processData(array $priceHistory, ?string $period): array
    {
        // Trier par date
        usort($priceHistory, fn($a, $b) => $a->getObservedAt() <=> $b->getObservedAt());

        // Filtrer par période
        $filtered = $this->filterByPeriod($priceHistory, $period);

        // Réduire les points si nécessaire
        $reduced = $this->reducePoints($filtered);

        return $this->extractChartData($reduced);
    }

    private function filterByPeriod(array $data, ?string $period): array
    {
        if (!$period || $period === 'all') {
            return $data;
        }

        $cutoffDate = match($period) {
            '7d' => new DateTime('-7 days'),
            '30d' => new DateTime('-30 days'),
            '90d' => new DateTime('-90 days'),
            default => new DateTime('-30 days')
        };

        return array_filter($data,
            fn($item) => $item->getObservedAt() >= $cutoffDate
        );
    }

    private function reducePoints(array $data): array
    {
        if (count($data) <= self::MAX_CHART_POINTS) {
            return $data;
        }

        // Prendre des échantillons réguliers
        $step = intval(count($data) / self::MAX_CHART_POINTS);
        $reduced = [];

        for ($i = 0; $i < count($data); $i += $step) {
            $reduced[] = $data[$i];
        }

        // Toujours inclure le dernier point
        if (end($reduced) !== end($data)) {
            $reduced[] = end($data);
        }

        return $reduced;
    }

    private function extractChartData(array $data): array
    {
        $labels = [];
        $priceData = [
            'x1' => [], 'x10' => [], 'x100' => [], 'x1000' => []
        ];

        foreach ($data as $item) {
            $labels[] = $item->getObservedAt()->format('d/m');

            foreach (self::PRICE_TYPES as $type => $config) {
                $method = $config['method'];
                $priceData[$type][] = $item->$method() ?? null;
            }
        }

        return ['labels' => $labels, 'data' => $priceData];
    }

    private function createDatasets(array $priceData): array
    {
        $datasets = [];

        foreach (self::PRICE_TYPES as $type => $config) {
            if (!empty(array_filter($priceData[$type]))) {
                $datasets[] = [
                    'label' => $type,
                    'data' => $priceData[$type],
                    'borderColor' => $config['color'],
                    'backgroundColor' => $config['color'] . '20',
                    'fill' => false,
                    'tension' => 0.1
                ];
            }
        }

        return $datasets;
    }

    private function getEmptyChart(): array
    {
        return [
            'labels' => [],
            'datasets' => [],
            'meta' => [
                'period' => 'all',
                'totalPoints' => 0,
                'displayedPoints' => 0
            ]
        ];
    }
}