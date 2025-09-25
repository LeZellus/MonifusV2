<?php

namespace App\Controller;

use App\Repository\LotUnitRepository;
use App\Service\ProfileCharacterService;
use App\Trait\CharacterSelectionTrait;
use App\Service\ExportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
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

        // Calculer les stats uniquement pour l'affichage (pas de récupération massive de données)
        $statsOnly = $lotUnitRepository->findSalesWithFilters(
            $this->getUser(),
            $selectedCharacter,
            $periodFilter
        );
        $stats = $lotUnitRepository->calculateSalesStats($statsOnly);

        // Configuration des colonnes DataTables
        $columnsConfig = [
            ['data' => 'soldAt', 'name' => 'soldAt', 'title' => 'Date Vente'],
            ['data' => 'itemName', 'name' => 'itemName', 'title' => 'Item', 'render' => 'renderItem'],
            ['data' => 'quantitySold', 'name' => 'quantitySold', 'title' => 'Quantité'],
            ['data' => 'sellPricePerLot', 'name' => 'sellPricePerLot', 'title' => 'Prix Attendu', 'render' => 'renderPrice'],
            ['data' => 'actualSellPrice', 'name' => 'actualSellPrice', 'title' => 'Prix Réel', 'render' => 'renderPrice'],
            ['data' => 'realizedProfit', 'name' => 'realizedProfit', 'title' => 'Profit Total', 'render' => 'renderProfit'],
            ['data' => 'performance', 'name' => 'performance', 'title' => 'Performance', 'render' => 'renderPerformance'],
            ['data' => 'notes', 'name' => 'notes', 'title' => 'Notes', 'orderable' => false],
            ['data' => 'id', 'name' => 'actions', 'title' => 'Actions', 'orderable' => false, 'searchable' => false, 'render' => 'renderActions']
        ];

        return $this->render('sales_history/index_custom.html.twig', [
            'characters' => $characters,
            'columns_config' => $columnsConfig,
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

    #[Route('/datatable', name: 'app_sales_history_datatable', methods: ['GET'])]
    public function datatable(
        Request $request,
        LotUnitRepository $lotUnitRepository,
        ProfileCharacterService $profileCharacterService
    ): JsonResponse {
        error_log('🔍 DataTable endpoint appelé avec: ' . json_encode($request->query->all()));

        try {
            $selectedCharacter = $profileCharacterService->getSelectedCharacter($this->getUser());
            error_log('👤 Personnage sélectionné: ' . ($selectedCharacter ? $selectedCharacter->getName() : 'aucun'));

            // Debug: Log l'état du personnage sélectionné
            if (!$selectedCharacter) {
                return new JsonResponse([
                    'draw' => (int) $request->query->get('draw', 1),
                    'recordsTotal' => 0,
                    'recordsFiltered' => 0,
                    'data' => [],
                    'error' => 'Aucun personnage sélectionné'
                ]);
            }

        // Récupérer les paramètres de notre table personnalisée
        $page = max(1, (int) $request->query->get('page', 1));
        $length = (int) $request->query->get('length', 25);
        $search = $request->query->get('search', '');
        $sortColumn = (int) $request->query->get('sortColumn', 0);
        $sortDirection = $request->query->get('sortDirection', 'desc');

        // Adapter les paramètres au format attendu par le repository
        $dtParams = [
            'draw' => $page, // Utiliser la page comme identifiant
            'start' => ($page - 1) * $length,
            'length' => $length,
            'search' => ['value' => $search],
            'order' => [['column' => $sortColumn, 'dir' => $sortDirection]]
        ];

        // Récupérer les filtres personnalisés - nettoyer la valeur de period
        $period = $request->query->get('period', '30');
        // S'assurer que period ne contient que des chiffres
        $period = preg_replace('/[^0-9]/', '', $period) ?: '30';
        error_log('🕒 Période nettoyée: ' . $period);

        // Appeler la nouvelle méthode optimisée
        $result = $lotUnitRepository->findSalesForDataTable(
            $this->getUser(),
            $selectedCharacter,
            $period,
            $dtParams
        );

        // Formater les données avec HTML pour notre table personnalisée
        $formattedData = array_map(function($sale) {
            $realizedProfit = ($sale['actualSellPrice'] - $sale['buyPricePerLot']) * $sale['quantitySold'];
            $expectedProfit = ($sale['sellPricePerLot'] - $sale['buyPricePerLot']) * $sale['quantitySold'];
            $performance = $sale['sellPricePerLot'] > 0 ? ($sale['actualSellPrice'] / $sale['sellPricePerLot'] * 100) : 0;

            return [
                $sale['soldAt']->format('d/m/Y H:i'),
                sprintf('<div class="flex items-center gap-2"><img src="%s" alt="%s" class="w-8 h-8 rounded"><span>%s</span></div>',
                    $sale['itemImage'] ?? '/images/items/default.png',
                    htmlspecialchars($sale['itemName']),
                    htmlspecialchars($sale['itemName'])
                ),
                number_format($sale['quantitySold'], 0, ',', ' '),
                sprintf('<span class="text-blue-400">%s K</span>', number_format($sale['sellPricePerLot'] / 1000, 0, ',', ' ')),
                sprintf('<span class="text-green-400">%s K</span>', number_format($sale['actualSellPrice'] / 1000, 0, ',', ' ')),
                sprintf('<span class="%s">%s%s K</span>',
                    $realizedProfit >= 0 ? 'text-green-400' : 'text-red-400',
                    $realizedProfit >= 0 ? '+' : '',
                    number_format($realizedProfit / 1000, 0, ',', ' ')
                ),
                sprintf('<span class="%s">%s%s%%</span>',
                    $performance >= 100 ? 'text-green-400' : ($performance >= 90 ? 'text-orange-400' : 'text-red-400'),
                    $performance >= 100 ? '+' : '',
                    number_format($performance - 100, 1)
                ),
                htmlspecialchars($sale['notes'] ?? ''),
                sprintf('<form method="POST" action="/lot-sale/%d/cancel" style="display:inline;" onsubmit="return confirm(\'Êtes-vous sûr de vouloir annuler cette vente ?\')"><button type="submit" class="text-red-400 hover:text-red-300 text-xs px-2 py-1 border border-red-400 rounded hover:bg-red-400 hover:text-white transition-colors">Annuler</button></form>', $sale['id'])
            ];
        }, $result['data']);

            return new JsonResponse([
                'draw' => $dtParams['draw'],
                'recordsTotal' => $result['recordsTotal'],
                'recordsFiltered' => $result['recordsFiltered'],
                'data' => $formattedData
            ]);

        } catch (\Exception $e) {
            // En cas d'erreur, renvoyer une réponse DataTables valide
            return new JsonResponse([
                'draw' => (int) $request->query->get('draw', 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Erreur: ' . $e->getMessage()
            ]);
        }
    }
}