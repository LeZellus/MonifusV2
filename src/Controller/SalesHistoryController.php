<?php

namespace App\Controller;

use App\Repository\LotUnitRepository;
use App\Service\ProfileCharacterService;
use App\Trait\CharacterSelectionTrait;
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
    use CharacterSelectionTrait;
    #[Route('/', name: 'app_sales_history_index')]
    public function index(
        Request $request,
        LotUnitRepository $lotUnitRepository,
        ProfileCharacterService $profileCharacterService
    ): Response {
        $periodFilter = $request->query->get('period', '30');
        [$selectedCharacter, $characters] = $this->getCharacterData($profileCharacterService);

        // Une seule ligne pour récupérer les ventes
        $sales = $lotUnitRepository->findSalesWithFilters(
            $this->getUser(), 
            $selectedCharacter, 
            $periodFilter
        );

        // Une seule ligne pour calculer les stats
        $stats = $lotUnitRepository->calculateSalesStats($sales);

        return $this->render('sales_history/index.html.twig', [
            'sales' => $sales,
            'characters' => $characters,
            ...$stats,
            'current_filters' => ['period' => $periodFilter],
        ]);
    }

    #[Route('/export', name: 'app_sales_history_export')]
    public function export(
        Request $request,
        LotUnitRepository $lotUnitRepository,
        ProfileCharacterService $profileCharacterService,
        ExportService $exportService
    ): Response {
        $periodFilter = $request->query->get('period', '30');
        $selectedCharacter = $profileCharacterService->getSelectedCharacter($this->getUser());

        // Même logique, zéro duplication
        $sales = $lotUnitRepository->findSalesWithFilters(
            $this->getUser(), 
            $selectedCharacter, 
            $periodFilter
        );

        if (empty($sales)) {
            $this->addFlash('warning', 'Aucune vente trouvée pour l\'export avec ces filtres.');
            return $this->redirectToRoute('app_sales_history_index', $request->query->all());
        }

        return $exportService->exportSalesToCsv($sales);
    }
}