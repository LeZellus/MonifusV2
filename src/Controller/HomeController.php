<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Repository\ClasseRepository;
use App\Repository\ServerRepository;
use App\Repository\LotGroupRepository;
use App\Repository\LotUnitRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class HomeController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ClasseRepository $classeRepository,
        private readonly ServerRepository $serverRepository,
        private readonly LotGroupRepository $lotGroupRepository,
        private readonly LotUnitRepository $lotUnitRepository,
        private readonly CacheInterface $cache
    ) {
    }

    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_trading_dashboard');
        }

        $stats = $this->cache->get('home_global_stats', function (ItemInterface $item) {
            $item->expiresAfter(1800); // Cache for 30 minutes
            return $this->calculateGlobalStats();
        });

        $basicCounts = $this->cache->get('home_basic_counts', function (ItemInterface $item) {
            $item->expiresAfter(3600); // Cache for 1 hour
            return [
                'users_count' => $this->userRepository->count([]),
                'classes_count' => $this->classeRepository->count([]),
                'servers_count' => $this->serverRepository->count([]),
            ];
        });

        return $this->render('home/index.html.twig', [
            'users_count' => $basicCounts['users_count'],
            'classes_count' => $basicCounts['classes_count'],
            'servers_count' => $basicCounts['servers_count'],
            'total_kamas_tracked' => $stats['total_kamas_tracked'],
            'total_lots_managed' => $stats['total_lots_managed'],
            'formatted_kamas' => $stats['formatted_kamas'],
            'formatted_lots' => $stats['formatted_lots'],
        ]);
    }

    private function calculateGlobalStats(): array
    {
        // Une seule requête pour récupérer toutes les stats des lots
        $lotStats = $this->lotGroupRepository->getGlobalStatistics();
        
        // Une seule requête pour les profits réalisés
        $totalProfitsRealized = $this->lotUnitRepository->getTotalRealizedProfits();

        // Calcul du total des kamas trackés
        $totalKamasTracked = $lotStats['total_invested'] 
                           + $totalProfitsRealized 
                           + $lotStats['total_potential_profit'];

        return [
            'total_kamas_tracked' => $totalKamasTracked,
            'total_lots_managed' => $lotStats['total_lots_managed'],
            'formatted_kamas' => $this->formatKamas($totalKamasTracked),
            'formatted_lots' => $this->formatNumber($lotStats['total_lots_managed']),
        ];
    }

    private function formatKamas(int $amount): string
    {
        return match (true) {
            $amount >= 1_000_000_000 => number_format($amount / 1_000_000_000, 1) . 'B',
            $amount >= 1_000_000 => number_format($amount / 1_000_000, 1) . 'M',
            $amount >= 1_000 => number_format($amount / 1_000, 1) . 'K',
            default => number_format($amount)
        };
    }

    private function formatNumber(int $number): string
    {
        return match (true) {
            $number >= 1_000_000 => number_format($number / 1_000_000, 1) . 'M',
            $number >= 1_000 => number_format($number / 1_000, 1) . 'K',
            default => number_format($number)
        };
    }
}