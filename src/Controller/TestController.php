<?php
// src/Controller/TestController.php
namespace App\Controller;

use App\Repository\DofusCharacterRepository;
use App\Repository\LotGroupRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TestController extends AbstractController
{
    #[Route('/test/performance', name: 'test_performance')]
    public function index(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_trading_dashboard');
        }

        $stats = $this->calculateGlobalStats();

        return $this->render('home/index.html.twig');
    }
}