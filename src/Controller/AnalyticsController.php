<?php

namespace App\Controller;

use App\Service\TradingCalculatorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/analytics')]
#[IsGranted('ROLE_USER')]
class AnalyticsController extends AbstractController
{
    #[Route('/', name: 'app_analytics_index')]
    public function index(TradingCalculatorService $calculator): Response
    {
        $stats = $calculator->getUserTradingStats($this->getUser());
        
        return $this->render('analytics/index.html.twig', [
            'stats' => $stats,
        ]);
    }
}