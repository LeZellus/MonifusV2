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
        
        return $this->render('trading/dashboard.html.twig', [
            'user' => $user,
            'trading_profiles_count' => $tradingProfileRepository->count(['user' => $user]),
            'characters_count' => $characterRepository->count([]),
            'lot_groups_count' => $lotGroupRepository->count([]),
        ]);
    }

    #[Route('/my-trading', name: 'app_trading_lots')]
    public function myTrading(): Response
    {
        return $this->render('trading/my_trading.html.twig');
    }

    #[Route('/surveillance', name: 'app_trading_surveillance')]
    public function surveillance(): Response
    {
        return $this->render('trading/surveillance.html.twig');
    }
}