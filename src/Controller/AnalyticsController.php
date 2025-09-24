<?php

namespace App\Controller;

use App\Service\TradingDashboardService;
use App\Service\ProfileCharacterService;
use App\Service\PerformanceAnalysisService;
use App\Trait\CharacterSelectionTrait;
use App\Enum\LotStatus;
use App\Repository\LotGroupRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/analytics')]
#[IsGranted('ROLE_USER')]
class AnalyticsController extends AbstractController
{
    use CharacterSelectionTrait;

    #[Route('/', name: 'app_analytics_index')]
    public function index(
        TradingDashboardService $dashboardService,
        ProfileCharacterService $profileCharacterService,
        PerformanceAnalysisService $performanceService,
        LotGroupRepository $lotGroupRepository
    ): Response {
        $user = $this->getUser();

        // Obtenir le personnage sélectionné ou rediriger
        $result = $this->getSelectedCharacterOrRedirect($profileCharacterService);
        if ($result instanceof Response) {
            return $result;
        }
        $selectedCharacter = $result;

        // Obtenir les données du sélecteur
        $selectorData = $profileCharacterService->getSelectorData($user);

        // Calculer les statistiques pour le personnage sélectionné
        $characterStats = null;
        $chartData = [];
        $performanceData = [];

        if ($selectedCharacter) {
            // Obtenir les statistiques depuis le repository (plus efficace)
            $repoStats = $lotGroupRepository->getCharacterCompleteStats($selectedCharacter);

            // Calculer les métriques additionnelles
            $averageProfit = $repoStats['soldLots'] > 0 ? $repoStats['realizedProfit'] / $repoStats['soldLots'] : 0;

            $characterStats = [
                'totalInvestment' => $repoStats['totalInvestment'],
                'totalProfit' => $repoStats['realizedProfit'],
                'activeLots' => $repoStats['activeLots'],
                'soldLots' => $repoStats['soldLots'],
                'totalTransactions' => $repoStats['totalTransactions'],
                'averageProfit' => $averageProfit,
                'bestTrade' => $this->getBestTrade($selectedCharacter),
                'worstTrade' => $this->getWorstTrade($selectedCharacter),
                'monthlyTrend' => $this->getMonthlyTrend($selectedCharacter),
            ];

            // Graphiques supprimés - pas nécessaires
            $chartData = [];
        }

        return $this->render('analytics/index.html.twig', [
            'selectorData' => $selectorData,
            'characterStats' => $characterStats,
            'chartData' => $chartData,
        ]);
    }

    // Méthodes d'aide pour calculer les métriques avancées

    private function getBestTrade($character): ?array
    {
        $bestTrade = null;
        $bestProfit = PHP_INT_MIN; // Commencer avec la valeur la plus petite possible

        foreach ($character->getLotGroups() as $lot) {
            if ($lot->getStatus() === LotStatus::SOLD) {
                foreach ($lot->getLotUnits() as $unit) {
                    $profit = ($unit->getActualSellPrice() * $unit->getQuantitySold()) -
                             ($lot->getBuyPricePerLot() * $unit->getQuantitySold());

                    if ($profit > $bestProfit) {
                        $bestProfit = $profit;
                        $bestTrade = [
                            'item' => $lot->getItem()->getName(),
                            'profit' => $profit,
                            'soldAt' => $unit->getSoldAt(),
                        ];
                    }
                }
            }
        }

        return $bestTrade;
    }

    private function getWorstTrade($character): ?array
    {
        $worstTrade = null;
        $worstProfit = PHP_INT_MAX; // Commencer avec la valeur la plus grande possible

        foreach ($character->getLotGroups() as $lot) {
            if ($lot->getStatus() === LotStatus::SOLD) {
                foreach ($lot->getLotUnits() as $unit) {
                    $profit = ($unit->getActualSellPrice() * $unit->getQuantitySold()) -
                             ($lot->getBuyPricePerLot() * $unit->getQuantitySold());

                    if ($profit < $worstProfit) {
                        $worstProfit = $profit;
                        $worstTrade = [
                            'item' => $lot->getItem()->getName(),
                            'profit' => $profit,
                            'soldAt' => $unit->getSoldAt(),
                        ];
                    }
                }
            }
        }

        return $worstTrade;
    }

    private function getMonthlyTrend($character): array
    {
        // Calculer la tendance mensuelle des profits
        $monthlyData = [];
        $currentMonth = new \DateTime('first day of this month');

        for ($i = 5; $i >= 0; $i--) {
            $month = clone $currentMonth;
            $month->modify("-$i months");
            $nextMonth = clone $month;
            $nextMonth->modify('+1 month');

            $monthProfit = 0;
            foreach ($character->getLotGroups() as $lot) {
                if ($lot->getStatus() === LotStatus::SOLD) {
                    foreach ($lot->getLotUnits() as $unit) {
                        if ($unit->getSoldAt() >= $month && $unit->getSoldAt() < $nextMonth) {
                            $monthProfit += ($unit->getActualSellPrice() * $unit->getQuantitySold()) -
                                           ($lot->getBuyPricePerLot() * $unit->getQuantitySold());
                        }
                    }
                }
            }

            $monthlyData[] = [
                'month' => $month->format('M Y'),
                'profit' => $monthProfit,
            ];
        }

        return $monthlyData;
    }

    // Méthodes graphiques supprimées
}