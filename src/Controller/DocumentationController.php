<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/docs')]
class DocumentationController extends AbstractController
{
    #[Route('/', name: 'app_docs_index')]
    public function index(): Response
    {
        return $this->render('docs/index.html.twig');
    }

    #[Route('/getting-started', name: 'app_docs_getting_started')]
    public function gettingStarted(): Response
    {
        return $this->render('docs/getting-started.html.twig');
    }

    #[Route('/trading-guide', name: 'app_docs_trading_guide')]
    public function tradingGuide(): Response
    {
        return $this->render('docs/trading-guide.html.twig');
    }

    #[Route('/market-watch', name: 'app_docs_market_watch')]
    public function marketWatch(): Response
    {
        return $this->render('docs/market-watch.html.twig');
    }

    #[Route('/lots', name: 'app_docs_lots')]
    public function lots(): Response
    {
        return $this->render('docs/lots.html.twig');
    }

    #[Route('/analytics', name: 'app_docs_analytics')]
    public function analytics(): Response
    {
        return $this->render('docs/analytics.html.twig');
    }

    #[Route('/faq', name: 'app_docs_faq')]
    public function faq(): Response
    {
        return $this->render('docs/faq.html.twig');
    }
}