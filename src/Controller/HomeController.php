<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\ClasseRepository;
use App\Repository\ServerRepository;
use App\Repository\LotGroupRepository;
use App\Repository\LotUnitRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        UserRepository $userRepository,
        ClasseRepository $classeRepository,
        ServerRepository $serverRepository,
        LotGroupRepository $lotGroupRepository,
        LotUnitRepository $lotUnitRepository
    ): Response {
        // Redirection si utilisateur connecté
        if ($this->getUser()) {
            return $this->redirectToRoute('app_trading_dashboard');
        }

        // Calculer les vraies statistiques
        $stats = $this->calculateRealStats($lotGroupRepository, $lotUnitRepository);

        return $this->render('home/index.html.twig', [
            'users_count' => $userRepository->count([]),
            'classes_count' => $classeRepository->count([]),
            'servers_count' => $serverRepository->count([]),
            'total_kamas_tracked' => $stats['total_kamas_tracked'],
            'total_lots_managed' => $stats['total_lots_managed'],
            'formatted_kamas' => $stats['formatted_kamas'],
            'formatted_lots' => $stats['formatted_lots'],
        ]);
    }

    private function calculateRealStats(
        LotGroupRepository $lotGroupRepository,
        LotUnitRepository $lotUnitRepository
    ): array {
        // 1. Calculer le total des kamas investis (lots disponibles)
        $totalInvested = $lotGroupRepository->createQueryBuilder('lg')
            ->select('SUM(lg.buyPricePerLot * lg.lotSize)')
            ->where('lg.status = :available')
            ->setParameter('available', \App\Enum\LotStatus::AVAILABLE)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // 2. Calculer le total des profits réalisés (ventes)
        $totalProfitsRealized = $lotUnitRepository->createQueryBuilder('lu')
            ->select('SUM((lu.actualSellPrice - lg.buyPricePerLot) * lu.quantitySold)')
            ->join('lu.lotGroup', 'lg')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // 3. Calculer le potentiel profit des lots disponibles
        $totalPotentialProfit = $lotGroupRepository->createQueryBuilder('lg')
            ->select('SUM((lg.sellPricePerLot - lg.buyPricePerLot) * lg.lotSize)')
            ->where('lg.status = :available')
            ->setParameter('available', \App\Enum\LotStatus::AVAILABLE)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // Total des kamas "trackés" = investi + profits réalisés + potentiel
        $totalKamasTracked = $totalInvested + $totalProfitsRealized + $totalPotentialProfit;

        // 4. Compter le nombre total de lots (disponibles + vendus)
        $totalLotsManaged = $lotGroupRepository->createQueryBuilder('lg')
            ->select('SUM(lg.lotSize)')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // Formatage pour l'affichage
        $formattedKamas = $this->formatKamas($totalKamasTracked);
        $formattedLots = $this->formatNumber($totalLotsManaged);

        return [
            'total_kamas_tracked' => $totalKamasTracked,
            'total_lots_managed' => $totalLotsManaged,
            'formatted_kamas' => $formattedKamas,
            'formatted_lots' => $formattedLots,
        ];
    }

    private function formatKamas(int $amount): string
    {
        if ($amount >= 1000000000) { // 1 milliard+
            return number_format($amount / 1000000000, 1) . 'B';
        } elseif ($amount >= 1000000) { // 1 million+
            return number_format($amount / 1000000, 1) . 'M';
        } elseif ($amount >= 1000) { // 1 millier+
            return number_format($amount / 1000, 1) . 'K';
        }
        
        return number_format($amount);
    }

    private function formatNumber(int $number): string
    {
        if ($number >= 1000000) {
            return number_format($number / 1000000, 1) . 'M';
        } elseif ($number >= 1000) {
            return number_format($number / 1000, 1) . 'K';
        }
        
        return number_format($number);
    }
}