<?php

namespace App\Controller;

use App\Entity\MarketWatch;
use App\Entity\DofusCharacter;
use App\Form\MarketWatchType;
use App\Service\ProfileCharacterService;
use App\Service\MarketWatchService;
use App\Trait\CharacterSelectionTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Repository\ItemRepository;
use App\Repository\MarketWatchRepository;
use App\Service\ChartDataService;

#[Route('/market-watch')]
#[IsGranted('ROLE_USER')]
class MarketWatchController extends AbstractController
{
    use CharacterSelectionTrait;
    #[Route('/', name: 'app_market_watch_index')]
    public function index(
        MarketWatchService $marketWatchService,
        ProfileCharacterService $profileCharacterService
    ): Response {
        [$selectedCharacter, $characters] = $this->getCharacterData($profileCharacterService);
        $itemsData = $marketWatchService->getItemsDataForCharacter($selectedCharacter);

        return $this->render('market_watch/index_custom.html.twig', [
            'character' => $selectedCharacter,
            'characters' => $characters,
        ]);
    }

    #[Route('/datatable', name: 'app_market_watch_datatable', methods: ['GET'])]
    public function datatable(
        Request $request,
        MarketWatchService $marketWatchService,
        ProfileCharacterService $profileCharacterService,
        MarketWatchRepository $marketWatchRepository
    ): Response {
        error_log('🔍 MarketWatch DataTable endpoint appelé avec: ' . json_encode($request->query->all()));

        try {
            $user = $this->getUser();
            error_log('🔐 Utilisateur connecté: ' . ($user ? $user->getUserIdentifier() : 'aucun'));

            $selectedCharacter = $profileCharacterService->getSelectedCharacter($user);
            error_log('👤 Personnage sélectionné: ' . ($selectedCharacter ? $selectedCharacter->getName() . ' (ID: ' . $selectedCharacter->getId() . ')' : 'aucun'));

            if (!$selectedCharacter) {
                return new JsonResponse([
                    'draw' => (int) $request->query->get('page', 1),
                    'recordsTotal' => 0,
                    'recordsFiltered' => 0,
                    'data' => [],
                    'mobile_cards' => '',
                    'error' => 'Aucun personnage sélectionné'
                ]);
            }

            // Récupérer les paramètres de notre table personnalisée
            $page = max(1, (int) $request->query->get('page', 1));
            $length = (int) $request->query->get('length', 25);
            $search = $request->query->get('search', '');
            $sortColumn = (int) $request->query->get('sortColumn', 0);
            $sortDirection = $request->query->get('sortDirection', 'desc');

            error_log('📊 Paramètres: page=' . $page . ', length=' . $length . ', search=' . $search);

            // Récupérer les observations individuelles plutôt que les données agrégées
            $allObservations = $marketWatchRepository->findByCharacterWithItems($selectedCharacter, $search);

            error_log('📋 Nombre total d\'observations trouvées: ' . count($allObservations));

            // Tri des observations
            $columns = ['item', 'pricePerUnit', 'pricePer10', 'pricePer100', 'pricePer1000', 'observedAt'];
            if (isset($columns[$sortColumn])) {
                $sortKey = $columns[$sortColumn];
                usort($allObservations, function($a, $b) use ($sortKey, $sortDirection) {
                    if ($sortKey === 'item') {
                        $result = strcmp($a->getItem()->getName(), $b->getItem()->getName());
                    } elseif ($sortKey === 'observedAt') {
                        $result = $a->getObservedAt() <=> $b->getObservedAt();
                    } else {
                        $methodName = 'get' . ucfirst($sortKey);
                        $valueA = method_exists($a, $methodName) ? $a->$methodName() : 0;
                        $valueB = method_exists($b, $methodName) ? $b->$methodName() : 0;
                        $result = ($valueA ?? 0) <=> ($valueB ?? 0);
                    }
                    return $sortDirection === 'desc' ? -$result : $result;
                });
            }

            $totalRecords = count($allObservations);

            // Pagination
            $start = ($page - 1) * $length;
            $pagedObservations = array_slice($allObservations, $start, $length);

            error_log('📄 Observations paginées: ' . count($pagedObservations));

            // Formater les données avec HTML
            $formattedData = array_map(function($observation) {
                return [
                    sprintf('<div class="flex items-center gap-2"><img src="%s" alt="%s" class="w-8 h-8 rounded"><span>%s</span></div>',
                        $observation->getItem()->getImgUrl() ?? '/images/items/default.png',
                        htmlspecialchars($observation->getItem()->getName()),
                        htmlspecialchars($observation->getItem()->getName())
                    ),
                    sprintf('<span class="text-green-400">%s K</span>', $observation->getPricePerUnit() ? number_format($observation->getPricePerUnit() / 1000, 1) : '-'),
                    sprintf('<span class="text-blue-400">%s K</span>', $observation->getPricePer10() ? number_format($observation->getPricePer10() / 1000, 1) : '-'),
                    sprintf('<span class="text-purple-400">%s K</span>', $observation->getPricePer100() ? number_format($observation->getPricePer100() / 1000, 1) : '-'),
                    sprintf('<span class="text-orange-400">%s K</span>', $observation->getPricePer1000() ? number_format($observation->getPricePer1000() / 1000, 1) : '-'),
                    $observation->getObservedAt()->format('d/m/Y H:i'),
                    sprintf('<div class="flex gap-2">
                        <a href="/market-watch/%d/edit" class="text-blue-400 hover:text-blue-300 text-xs px-2 py-1 border border-blue-400 rounded">Modifier</a>
                        <form method="POST" action="/market-watch/%d/delete" style="display:inline;" onsubmit="return confirm(\'Supprimer cette observation ?\')">
                            <button type="submit" class="text-red-400 hover:text-red-300 text-xs px-2 py-1 border border-red-400 rounded">Supprimer</button>
                        </form>
                    </div>',
                        $observation->getId(),
                        $observation->getId()
                    )
                ];
            }, $pagedObservations);

            // Générer les cartes mobiles HTML
            $mobileCards = '';
            foreach ($pagedObservations as $observation) {
                $itemData = [
                    'item' => $observation->getItem(),
                    'marketWatch' => $observation,
                    'pricePerUnit' => $observation->getPricePerUnit(),
                    'pricePer10' => $observation->getPricePer10(),
                    'pricePer100' => $observation->getPricePer100(),
                    'pricePer1000' => $observation->getPricePer1000()
                ];
                $mobileCards .= $this->renderView('market_watch/_observation_mobile_card.html.twig', ['item' => $itemData]);
            }

            return new JsonResponse([
                'draw' => $page,
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $totalRecords, // Pour l'instant même valeur
                'data' => $formattedData,
                'mobile_cards' => $mobileCards
            ]);

        } catch (\Exception $e) {
            error_log('💥 Erreur MarketWatch DataTable: ' . $e->getMessage());
            error_log('📋 Stack trace: ' . $e->getTraceAsString());
            return new JsonResponse([
                'draw' => (int) $request->query->get('page', 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'mobile_cards' => '',
                'error' => 'Erreur: ' . $e->getMessage()
            ]);
        }
    }

    
    #[Route('/new/{itemId}', name: 'app_market_watch_new', requirements: ['itemId' => '\d+'], defaults: ['itemId' => null])]
    public function new(
        Request $request,
        ItemRepository $itemRepository,
        MarketWatchService $marketWatchService,
        ProfileCharacterService $profileCharacterService,
        ?int $itemId = null
    ): Response {
        $result = $this->getSelectedCharacterOrRedirect($profileCharacterService);
        if ($result instanceof Response) {
            return $result;
        }
        $selectedCharacter = $result;

        // Gestion du préremplissage d'item
        $preselectedItem = null;
        if ($itemId) {
            $preselectedItem = $itemRepository->find($itemId);
            if (!$preselectedItem) {
                $this->addFlash('error', 'Ressource introuvable.');
                return $this->redirectToRoute('app_market_watch_index');
            }
        }

        $marketWatch = new MarketWatch();

        $formOptions = ['is_edit' => false];
        if ($preselectedItem) {
            $formOptions['preselected_item'] = $preselectedItem;
        }

        $form = $this->createForm(MarketWatchType::class, $marketWatch, $formOptions);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $formItemId = !$preselectedItem ? $form->get('item')->getData() : null;

            if ($marketWatchService->createMarketWatch($selectedCharacter, $marketWatch, $preselectedItem, $formItemId)) {
                $itemName = $marketWatch->getItem()->getName();
                $this->addFlash('success', "Observation ajoutée pour {$itemName} !");
                return $this->redirectToRoute('app_market_watch_index');
            } else {
                $this->addFlash('error', 'Erreur lors de la création de l\'observation.');
            }
        }

        [$selectedCharacter, $characters] = $this->getCharacterData($profileCharacterService);

        return $this->render('market_watch/new.html.twig', [
            'form' => $form,
            'character' => $selectedCharacter,
            'characters' => $characters,
            'preselected_item' => $preselectedItem,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_market_watch_edit')]
    public function edit(
        MarketWatch $marketWatch,
        Request $request,
        MarketWatchService $marketWatchService,
        ProfileCharacterService $profileCharacterService
    ): Response {
        $result = $this->getSelectedCharacterOrRedirect($profileCharacterService);
        if ($result instanceof Response) {
            return $result;
        }
        $selectedCharacter = $result;

        if (!$marketWatchService->canUserAccessMarketWatch($marketWatch, $selectedCharacter)) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(MarketWatchType::class, $marketWatch, ['is_edit' => true]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $marketWatchService->updateMarketWatch($marketWatch);
            $this->addFlash('success', 'Observation modifiée avec succès !');
            return $this->redirectToRoute('app_market_watch_index');
        }

        [$selectedCharacter, $characters] = $this->getCharacterData($profileCharacterService);

        return $this->render('market_watch/edit.html.twig', [
            'form' => $form,
            'market_watch' => $marketWatch,
            'characters' => $characters,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_market_watch_delete', methods: ['POST'])]
    public function delete(
        MarketWatch $marketWatch,
        MarketWatchService $marketWatchService,
        ProfileCharacterService $profileCharacterService
    ): Response {
        $result = $this->getSelectedCharacterOrRedirect($profileCharacterService);
        if ($result instanceof Response) {
            return $result;
        }
        $selectedCharacter = $result;

        if (!$marketWatchService->canUserAccessMarketWatch($marketWatch, $selectedCharacter)) {
            throw $this->createAccessDeniedException();
        }

        $marketWatchService->deleteMarketWatch($marketWatch);
        $this->addFlash('success', 'Observation supprimée avec succès !');
        return $this->redirectToRoute('app_market_watch_index');
    }

    #[Route('/item/{itemId}/delete-all', name: 'app_market_watch_delete_all_for_item', methods: ['POST'])]
    public function deleteAllForItem(
        int $itemId,
        MarketWatchService $marketWatchService,
        ProfileCharacterService $profileCharacterService
    ): Response {
        $result = $this->getSelectedCharacterOrRedirect($profileCharacterService, 'Aucun personnage sélectionné.');
        if ($result instanceof Response) {
            return $result;
        }
        $selectedCharacter = $result;

        $count = $marketWatchService->deleteAllObservationsForItem($selectedCharacter, $itemId);

        if ($count === 0) {
            $this->addFlash('warning', 'Aucune observation à supprimer.');
        } else {
            $this->addFlash('success', "{$count} observation(s) supprimée(s).");
        }

        return $this->redirectToRoute('app_market_watch_index');
    }

    #[Route('/item/{itemId}/history', name: 'app_market_watch_history')]
    public function itemHistory(
        int $itemId,
        MarketWatchService $marketWatchService,
        ProfileCharacterService $profileCharacterService,
        ChartDataService $chartDataService
    ): Response {
        $result = $this->getSelectedCharacterOrRedirect($profileCharacterService, 'Aucun personnage sélectionné.');
        if ($result instanceof Response) {
            return $result;
        }
        $selectedCharacter = $result;

        $priceHistory = $marketWatchService->getPriceHistoryForItem($selectedCharacter, $itemId);

        if (empty($priceHistory)) {
            $this->addFlash('warning', 'Aucun historique de prix pour cet item.');
            return $this->redirectToRoute('app_market_watch_index');
        }

        $item = $priceHistory[0]->getItem();
        $averages = $marketWatchService->calculatePriceAverages($priceHistory);
        $chartData = $chartDataService->prepareMarketWatchChartData($priceHistory, 'all');

        return $this->render('market_watch/history.html.twig', [
            'item' => $item,
            'price_history' => $priceHistory,
            'character' => $selectedCharacter,
            'averages' => $averages,
            'chart_data' => $chartData,
        ]);
    }

    #[Route('/search', name: 'app_market_watch_search', methods: ['GET'])]
    public function search(
        MarketWatchService $marketWatchService,
        ProfileCharacterService $profileCharacterService,
        Request $request
    ): JsonResponse {
        $selectedCharacter = $profileCharacterService->getSelectedCharacter($this->getUser());

        if (!$selectedCharacter) {
            return $this->createCharacterErrorResponse('Aucun personnage sélectionné');
        }

        $searchQuery = trim($request->query->get('q', ''));
        $itemsData = $marketWatchService->getItemsDataForCharacter($selectedCharacter, $searchQuery);

        // Generate mobile cards HTML for mobile view
        $mobileCards = '';
        foreach ($pagedData as $itemData) {
            $mobileCards .= $this->renderView('market_watch/_observation_mobile_card.html.twig', ['item' => $itemData]);
        }

        return new JsonResponse([
            'draw' => $page,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalRecords,
            'data' => $formattedData,
            'mobile_cards' => $mobileCards
        ]);
    }

}