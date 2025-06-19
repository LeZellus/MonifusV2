<?php

namespace App\Controller;

use App\Repository\LotUnitRepository;
use App\Repository\DofusCharacterRepository;
use App\Service\ExportService; 
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/sales-history')]
#[IsGranted('ROLE_USER')]
class SalesHistoryController extends AbstractController
{
    #[Route('/', name: 'app_sales_history_index')]
    public function index(
        Request $request,
        LotUnitRepository $lotUnitRepository,
        DofusCharacterRepository $characterRepository
    ): Response {
        // Récupérer les filtres
        $serverFilter = $request->query->get('server');
        $periodFilter = $request->query->get('period', '30'); // 30 jours par défaut

        // Récupérer tous les personnages de l'utilisateur
        $characters = $characterRepository->createQueryBuilder('c')
            ->join('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->setParameter('user', $this->getUser())
            ->getQuery()
            ->getResult();

        // Construire la requête avec filtres
        $qb = $lotUnitRepository->createQueryBuilder('lu')
            ->join('lu.lotGroup', 'lg')
            ->join('lg.dofusCharacter', 'c')
            ->join('lg.item', 'i')
            ->join('c.tradingProfile', 'tp')
            ->join('c.server', 's')
            ->where('tp.user = :user')
            ->setParameter('user', $this->getUser());

        // Filtre par serveur
        if ($serverFilter) {
            $qb->andWhere('s.id = :server')
            ->setParameter('server', $serverFilter);
        }

        // Filtre par période
        if ($periodFilter !== 'all') {
            $date = new \DateTime();
            $date->modify("-{$periodFilter} days");
            $qb->andWhere('lu.soldAt >= :date')
            ->setParameter('date', $date);
        }

        $sales = $qb->orderBy('lu.soldAt', 'DESC')->getQuery()->getResult();

        // Calculer les statistiques
        $totalRealizedProfit = 0;
        $totalExpectedProfit = 0;
        foreach ($sales as $sale) {
            $realizedProfit = $sale->getActualSellPrice() - $sale->getLotGroup()->getBuyPricePerLot();
            $expectedProfit = $sale->getLotGroup()->getSellPricePerLot() - $sale->getLotGroup()->getBuyPricePerLot();
            
            $totalRealizedProfit += $realizedProfit;
            $totalExpectedProfit += $expectedProfit;
        }

        return $this->render('sales_history/index.html.twig', [
            'sales' => $sales,
            'characters' => $characters,
            'total_realized_profit' => $totalRealizedProfit,
            'total_expected_profit' => $totalExpectedProfit,
            'profit_difference' => $totalRealizedProfit - $totalExpectedProfit,
            'current_filters' => [
                'server' => $serverFilter,
                'period' => $periodFilter,
            ],
        ]);
    }

    #[Route('/export', name: 'app_sales_history_export')]
    public function export(
        Request $request,
        LotUnitRepository $lotUnitRepository,
        DofusCharacterRepository $characterRepository,
        ExportService $exportService
    ): Response {
        // Récupérer les mêmes filtres que dans index()
        $serverFilter = $request->query->get('server');
        $periodFilter = $request->query->get('period', '30');

        // Récupérer tous les personnages de l'utilisateur
        $characters = $characterRepository->createQueryBuilder('c')
            ->join('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->setParameter('user', $this->getUser())
            ->getQuery()
            ->getResult();

        if (empty($characters)) {
            $this->addFlash('warning', 'Aucun personnage trouvé pour l\'export.');
            return $this->redirectToRoute('app_sales_history_index');
        }

        // Construire la même requête avec filtres que dans index()
        $qb = $lotUnitRepository->createQueryBuilder('lu')
            ->join('lu.lotGroup', 'lg')
            ->join('lg.dofusCharacter', 'c')
            ->join('lg.item', 'i')
            ->join('c.tradingProfile', 'tp')
            ->join('c.server', 's')
            ->where('tp.user = :user')
            ->setParameter('user', $this->getUser());

        // Filtre par serveur
        if ($serverFilter) {
            $qb->andWhere('s.id = :server')
            ->setParameter('server', $serverFilter);
        }

        // Filtre par période
        if ($periodFilter !== 'all') {
            $date = new \DateTime();
            $date->modify("-{$periodFilter} days");
            $qb->andWhere('lu.soldAt >= :date')
            ->setParameter('date', $date);
        }

        $sales = $qb->orderBy('lu.soldAt', 'DESC')->getQuery()->getResult();

        if (empty($sales)) {
            $this->addFlash('warning', 'Aucune vente trouvée pour l\'export avec ces filtres.');
            return $this->redirectToRoute('app_sales_history_index', $request->query->all());
        }

        return $exportService->exportSalesToCsv($sales);
    }
}