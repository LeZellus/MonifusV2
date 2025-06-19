<?php

namespace App\Controller;

use App\Repository\LotUnitRepository;
use App\Repository\DofusCharacterRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/sales-history')]
#[IsGranted('ROLE_USER')]
class SalesHistoryController extends AbstractController
{
    #[Route('/', name: 'app_sales_history_index')]
    public function index(
        LotUnitRepository $lotUnitRepository,
        DofusCharacterRepository $characterRepository
    ): Response {
        // Récupérer tous les personnages de l'utilisateur
        $characters = $characterRepository->createQueryBuilder('c')
            ->join('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->setParameter('user', $this->getUser())
            ->getQuery()
            ->getResult();

        // Récupérer toutes les ventes des personnages de l'utilisateur
        $sales = [];
        $totalRealizedProfit = 0;
        $totalExpectedProfit = 0;

        if (!empty($characters)) {
            $sales = $lotUnitRepository->createQueryBuilder('lu')
                ->join('lu.lotGroup', 'lg')
                ->join('lg.dofusCharacter', 'c')
                ->join('c.tradingProfile', 'tp')
                ->where('tp.user = :user')
                ->setParameter('user', $this->getUser())
                ->orderBy('lu.soldAt', 'DESC')
                ->getQuery()
                ->getResult();

            // Calculer les profits totaux
            foreach ($sales as $sale) {
                $realizedProfit = $sale->getActualSellPrice() - $sale->getLotGroup()->getBuyPricePerLot();
                $expectedProfit = $sale->getLotGroup()->getSellPricePerLot() - $sale->getLotGroup()->getBuyPricePerLot();
                
                $totalRealizedProfit += $realizedProfit;
                $totalExpectedProfit += $expectedProfit;
            }
        }

        return $this->render('sales_history/index.html.twig', [
            'sales' => $sales,
            'characters' => $characters,
            'total_realized_profit' => $totalRealizedProfit,
            'total_expected_profit' => $totalExpectedProfit,
            'profit_difference' => $totalRealizedProfit - $totalExpectedProfit,
        ]);
    }
}