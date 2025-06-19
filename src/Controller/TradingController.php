<?php

namespace App\Controller;

use App\Repository\TradingProfileRepository;
use App\Repository\DofusCharacterRepository;
use App\Repository\LotGroupRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Service\TradingCalculatorService;
use App\Service\NotificationService;

#[Route('/trading')]
#[IsGranted('ROLE_USER')]
class TradingController extends AbstractController
{
    #[Route('/dashboard', name: 'app_trading_dashboard')]
    public function dashboard(
        TradingProfileRepository $tradingProfileRepository,
        TradingCalculatorService $calculator,
        NotificationService $notificationService
    ): Response {
        $user = $this->getUser();
        $stats = $calculator->getUserTradingStats($user);
        $notifications = $notificationService->getUserNotifications($user);
        
        return $this->render('trading/dashboard.html.twig', [
            'user' => $user,
            'trading_profiles_count' => $tradingProfileRepository->count(['user' => $user]),
            'stats' => $stats,
            'notifications' => $notifications,
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