<?php

namespace App\Controller;

use App\Repository\TradingProfileRepository;
use App\Repository\DofusCharacterRepository;
use App\Repository\LotGroupRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/trading')]
#[IsGranted('ROLE_USER')]
class TradingController extends AbstractController
{
    #[Route('/dashboard', name: 'app_trading_dashboard')]
    public function dashboard(
        TradingProfileRepository $tradingProfileRepository,
        DofusCharacterRepository $characterRepository,
        LotGroupRepository $lotGroupRepository
    ): Response {
        $user = $this->getUser();
        
        // Récupérer les personnages de l'utilisateur
        $characters = $characterRepository->createQueryBuilder('c')
            ->join('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        // Statistiques globales
        $totalLots = 0;
        $availableLots = 0;
        $soldLots = 0;
        $totalProfit = 0;

        if (!empty($characters)) {
            $lotStats = $lotGroupRepository->createQueryBuilder('lg')
                ->select('COUNT(lg.id) as total')
                ->addSelect('SUM(CASE WHEN lg.status = :available THEN 1 ELSE 0 END) as available')
                ->addSelect('SUM(CASE WHEN lg.status = :sold THEN 1 ELSE 0 END) as sold')
                ->addSelect('SUM(lg.sellPricePerLot - lg.buyPricePerLot) as profit')
                ->join('lg.dofusCharacter', 'c')
                ->join('c.tradingProfile', 'tp')
                ->where('tp.user = :user')
                ->setParameter('user', $user)
                ->setParameter('available', \App\Enum\LotStatus::AVAILABLE)
                ->setParameter('sold', \App\Enum\LotStatus::SOLD)
                ->getQuery()
                ->getOneOrNullResult();

            if ($lotStats) {
                $totalLots = $lotStats['total'] ?? 0;
                $availableLots = $lotStats['available'] ?? 0;
                $soldLots = $lotStats['sold'] ?? 0;
                $totalProfit = $lotStats['profit'] ?? 0;
            }
        }
        
        return $this->render('trading/dashboard.html.twig', [
            'user' => $user,
            'trading_profiles_count' => $tradingProfileRepository->count(['user' => $user]),
            'characters_count' => count($characters),
            'lot_groups_count' => $totalLots,
            'available_lots' => $availableLots,
            'sold_lots' => $soldLots,
            'total_profit' => $totalProfit,
        ]);
    }

    #[Route('/my-trading', name: 'app_trading_lots')]
    public function myTrading(): Response
    {
        return $this->redirectToRoute('app_lot_index');
    }

    #[Route('/surveillance', name: 'app_trading_surveillance')]
    public function surveillance(): Response
    {
        return $this->redirectToRoute('app_market_watch_index');
    }
}