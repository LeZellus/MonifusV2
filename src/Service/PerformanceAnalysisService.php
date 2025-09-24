<?php

namespace App\Service;

use App\Entity\User;
use App\ValueObject\PerformanceMetrics;
use App\Repository\LotUnitRepository;

/**
 * Service dédié à l'analyse des performances temporelles
 */
class PerformanceAnalysisService
{
    public function __construct(
        private LotUnitRepository $lotUnitRepository
    ) {}

    public function analyzeUserPerformance(User $user): PerformanceMetrics
    {
        $weeklyData = $this->getWeeklyPerformance($user);
        $monthlyData = $this->getMonthlyPerformance($user);
        $trends = $this->calculateTrends($user);

        return new PerformanceMetrics(
            weeklyData: $weeklyData,
            monthlyData: $monthlyData,
            weekTrend: $trends['weekTrend'],
            bestDay: $trends['bestDay'],
            bestDayProfit: $trends['bestDayProfit']
        );
    }

    private function getWeeklyPerformance(User $user): array
    {
        $weeklyStats = $this->lotUnitRepository->createQueryBuilder('lu')
            ->select('
                DATE(lu.soldAt) as day,
                SUM(lu.actualSellPrice * lu.quantitySold) as revenue,
                SUM(lg.buyPricePerLot * lu.quantitySold) as cost
            ')
            ->join('lu.lotGroup', 'lg')
            ->join('lg.dofusCharacter', 'c')
            ->join('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->andWhere('lu.soldAt >= :weekAgo')
            ->setParameter('user', $user)
            ->setParameter('weekAgo', new \DateTime('-7 days'))
            ->groupBy('day')
            ->orderBy('day', 'DESC')
            ->getQuery()
            ->getArrayResult();

        return array_map(function($day) {
            return [
                'date' => $day['day'],
                'profit' => $day['revenue'] - $day['cost'],
                'revenue' => $day['revenue'],
                'transactions' => 1 // Approximation
            ];
        }, $weeklyStats);
    }

    private function getMonthlyPerformance(User $user): array
    {
        $monthlyStats = $this->lotUnitRepository->createQueryBuilder('lu')
            ->select('
                DATE_FORMAT(lu.soldAt, \'%Y-%m\') as month,
                SUM(lu.actualSellPrice * lu.quantitySold) as revenue,
                SUM(lg.buyPricePerLot * lu.quantitySold) as cost,
                COUNT(lu.id) as transactions
            ')
            ->join('lu.lotGroup', 'lg')
            ->join('lg.dofusCharacter', 'c')
            ->join('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->andWhere('lu.soldAt >= :monthsAgo')
            ->setParameter('user', $user)
            ->setParameter('monthsAgo', new \DateTime('-12 months'))
            ->groupBy('month')
            ->orderBy('month', 'DESC')
            ->getQuery()
            ->getArrayResult();

        return array_map(function($month) {
            return [
                'period' => $month['month'],
                'profit' => $month['revenue'] - $month['cost'],
                'revenue' => $month['revenue'],
                'transactions' => $month['transactions']
            ];
        }, $monthlyStats);
    }

    private function calculateTrends(User $user): array
    {
        // Calcul de tendance hebdomadaire
        $currentWeekProfit = $this->getWeekProfit($user, 0);
        $lastWeekProfit = $this->getWeekProfit($user, 1);

        $weekTrend = $lastWeekProfit > 0
            ? (($currentWeekProfit - $lastWeekProfit) / $lastWeekProfit) * 100
            : 0;

        // Meilleur jour
        $bestDayData = $this->lotUnitRepository->createQueryBuilder('lu')
            ->select('
                DATE(lu.soldAt) as day,
                SUM(lu.actualSellPrice * lu.quantitySold - lg.buyPricePerLot * lu.quantitySold) as profit
            ')
            ->join('lu.lotGroup', 'lg')
            ->join('lg.dofusCharacter', 'c')
            ->join('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->andWhere('lu.soldAt >= :monthAgo')
            ->setParameter('user', $user)
            ->setParameter('monthAgo', new \DateTime('-30 days'))
            ->groupBy('day')
            ->orderBy('profit', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return [
            'weekTrend' => $weekTrend,
            'bestDay' => $bestDayData['day'] ?? 'N/A',
            'bestDayProfit' => $bestDayData['profit'] ?? 0
        ];
    }

    private function getWeekProfit(User $user, int $weeksAgo): int
    {
        $startDate = new \DateTime("-" . ($weeksAgo + 1) . " weeks");
        $endDate = new \DateTime("-{$weeksAgo} weeks");

        $result = $this->lotUnitRepository->createQueryBuilder('lu')
            ->select('SUM(lu.actualSellPrice * lu.quantitySold - lg.buyPricePerLot * lu.quantitySold) as profit')
            ->join('lu.lotGroup', 'lg')
            ->join('lg.dofusCharacter', 'c')
            ->join('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->andWhere('lu.soldAt BETWEEN :start AND :end')
            ->setParameter('user', $user)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }
}