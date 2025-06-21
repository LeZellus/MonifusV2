<?php

namespace App\Controller;

use App\Service\TradingCalculatorService;
use App\Service\CharacterSelectionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/analytics')]
#[IsGranted('ROLE_USER')]
class AnalyticsController extends AbstractController
{
    #[Route('/', name: 'app_analytics_index')]
    public function index(
        TradingCalculatorService $calculator,
        CharacterSelectionService $characterService
    ): Response {
        $selectedCharacter = $characterService->getSelectedCharacter($this->getUser());
        $stats = $calculator->getUserTradingStats($this->getUser());
        
        return $this->render('analytics/index.html.twig', [
            'stats' => $stats,
            'selectedCharacter' => $selectedCharacter,
        ]);
    }
}