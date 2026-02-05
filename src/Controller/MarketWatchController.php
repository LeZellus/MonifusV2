<?php

namespace App\Controller;

use App\Entity\MarketWatch;
use App\Entity\DofusCharacter;
use App\Form\MarketWatchType;
use App\Service\ProfileCharacterService;
use App\Service\MarketWatchService;
use App\Service\KamasFormatterService;
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
        Request $request,
        MarketWatchService $marketWatchService,
        ProfileCharacterService $profileCharacterService
    ): Response {
        [$selectedCharacter, $characters] = $this->getCharacterData($profileCharacterService);

        // Récupérer le filtre de période (défaut: 30 jours)
        $period = $request->query->get('period', '30');
        if ($period !== 'all') {
            $period = preg_replace('/[^0-9]/', '', $period) ?: '30';
        }

        // Récupérer le filtre de serveur (mode admin uniquement)
        $serverId = $request->query->get('server');
        $serverId = $serverId ? (int) $serverId : null;

        // Mode admin : données de tous les joueurs
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        $servers = [];
        if ($isAdmin) {
            $stats = $marketWatchService->calculateGlobalMarketWatchStats($period, $serverId);
            $servers = $marketWatchService->getServersWithObservations();
        } else {
            $stats = $marketWatchService->calculateMarketWatchStats($selectedCharacter, $period);
        }

        return $this->render('market_watch/index_custom.html.twig', [
            'character' => $selectedCharacter,
            'characters' => $characters,
            'is_admin_view' => $isAdmin,
            'current_period' => $period,
            'current_server' => $serverId,
            'servers' => $servers,
            ...$stats,
        ]);
    }

    #[Route('/datatable', name: 'app_market_watch_datatable', methods: ['GET'])]
    public function datatable(
        Request $request,
        MarketWatchService $marketWatchService,
        ProfileCharacterService $profileCharacterService,
        MarketWatchRepository $marketWatchRepository,
        KamasFormatterService $kamasFormatter
    ): Response {
        error_log('🔍 MarketWatch DataTable endpoint appelé avec: ' . json_encode($request->query->all()));

        try {
            $user = $this->getUser();
            $isAdmin = $this->isGranted('ROLE_ADMIN');
            error_log('🔐 Utilisateur connecté: ' . ($user ? $user->getUserIdentifier() : 'aucun') . ' (Admin: ' . ($isAdmin ? 'oui' : 'non') . ')');

            $selectedCharacter = $profileCharacterService->getSelectedCharacter($user);
            error_log('👤 Personnage sélectionné: ' . ($selectedCharacter ? $selectedCharacter->getName() . ' (ID: ' . $selectedCharacter->getId() . ')' : 'aucun'));

            // Mode admin : pas besoin de personnage sélectionné
            if (!$isAdmin && !$selectedCharacter) {
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

            // Récupérer le filtre de période
            $period = $request->query->get('period', '30');
            if ($period !== 'all') {
                $period = preg_replace('/[^0-9]/', '', $period) ?: '30';
            }

            // Récupérer le filtre de serveur (mode admin uniquement)
            $serverId = $request->query->get('server');
            $serverId = $serverId ? (int) $serverId : null;

            error_log('📊 Paramètres: page=' . $page . ', length=' . $length . ', search=' . $search . ', period=' . $period . ', server=' . ($serverId ?? 'all'));

            // Mode admin : données de tous les joueurs, sinon données du personnage
            if ($isAdmin) {
                $allItemsData = $marketWatchService->getGlobalItemsData($search, $period, $serverId);
                error_log('👑 Mode ADMIN: récupération des données globales');
            } else {
                $allItemsData = $marketWatchService->getItemsDataForCharacter($selectedCharacter, $search, $period);
            }

            error_log('📋 Nombre total d\'items trouvés: ' . count($allItemsData));

            // Tri des données agrégées
            $columns = ['item', 'avg_price_unit', 'avg_price_10', 'avg_price_100', 'avg_price_1000', 'latest_date'];
            if (isset($columns[$sortColumn])) {
                $sortKey = $columns[$sortColumn];
                usort($allItemsData, function($a, $b) use ($sortKey, $sortDirection) {
                    if ($sortKey === 'item') {
                        $result = strcmp($a['item']->getName(), $b['item']->getName());
                    } elseif ($sortKey === 'latest_date') {
                        $result = $a['latest_date'] <=> $b['latest_date'];
                    } else {
                        $result = ($a[$sortKey] ?? 0) <=> ($b[$sortKey] ?? 0);
                    }
                    return $sortDirection === 'desc' ? -$result : $result;
                });
            }

            $totalRecords = count($allItemsData);

            // Pagination
            $start = ($page - 1) * $length;
            $pagedItemsData = array_slice($allItemsData, $start, $length);

            error_log('📄 Items paginés: ' . count($pagedItemsData));

            // Formater les données avec HTML et liens vers l'historique
            $formattedData = array_map(function($itemData) use ($kamasFormatter, $isAdmin) {
                $historyUrl = $this->generateUrl('app_market_watch_history', ['itemId' => $itemData['item']->getId()]);

                // En mode admin, afficher le nombre de joueurs dans le nom de l'item
                $playersInfo = '';
                if ($isAdmin && isset($itemData['players_count'])) {
                    $playersInfo = sprintf('<div class="text-xs text-yellow-400">%d joueur%s</div>',
                        $itemData['players_count'],
                        $itemData['players_count'] > 1 ? 's' : ''
                    );
                }

                $row = [
                    sprintf('<div class="flex items-center gap-2">
                        <img src="%s" alt="%s" class="w-8 h-8 rounded">
                        <div>
                            <a href="%s" class="text-white hover:text-blue-400 transition-colors">%s</a>
                            %s
                        </div>
                    </div>',
                        $itemData['item']->getImgUrl() ?? '/images/items/default.png',
                        htmlspecialchars($itemData['item']->getName()),
                        $historyUrl,
                        htmlspecialchars($itemData['item']->getName()),
                        $playersInfo
                    ),
                    sprintf('<span class="text-green-400">%s</span><div class="text-gray-500 text-xs">%d obs</div>',
                        $itemData['avg_price_unit'] ? $kamasFormatter->formatWithHtml((int)$itemData['avg_price_unit']) : '-',
                        $itemData['price_unit_count'] ?? 0
                    ),
                    sprintf('<span class="text-blue-400">%s</span><div class="text-gray-500 text-xs">%d obs</div>',
                        $itemData['avg_price_10'] ? $kamasFormatter->formatWithHtml((int)$itemData['avg_price_10']) : '-',
                        $itemData['price_10_count'] ?? 0
                    ),
                    sprintf('<span class="text-purple-400">%s</span><div class="text-gray-500 text-xs">%d obs</div>',
                        $itemData['avg_price_100'] ? $kamasFormatter->formatWithHtml((int)$itemData['avg_price_100']) : '-',
                        $itemData['price_100_count'] ?? 0
                    ),
                    sprintf('<span class="text-orange-400">%s</span><div class="text-gray-500 text-xs">%d obs</div>',
                        $itemData['avg_price_1000'] ? $kamasFormatter->formatWithHtml((int)$itemData['avg_price_1000']) : '-',
                        $itemData['price_1000_count'] ?? 0
                    ),
                    $itemData['latest_date']->format('d/m/Y H:i'),
                ];

                // En mode admin, pas de boutons d'action (lecture seule)
                if ($isAdmin) {
                    $row[] = sprintf('<a href="%s" class="text-blue-400 hover:text-blue-300 text-xs px-2 py-1 border border-blue-400 rounded">Voir détails</a>', $historyUrl);
                } else {
                    $row[] = sprintf('<div class="flex gap-2">
                        <a href="%s" class="text-blue-400 hover:text-blue-300 text-xs px-2 py-1 border border-blue-400 rounded">Historique</a>
                        <a href="%s" class="text-green-400 hover:text-green-300 text-xs px-2 py-1 border border-green-400 rounded">Ajouter</a>
                        <form method="POST" action="%s" style="display:inline;" onsubmit="return confirm(\'Supprimer toutes les observations pour cette ressource ?\')">
                            <button type="submit" class="text-red-400 hover:text-red-300 text-xs px-2 py-1 border border-red-400 rounded">Supprimer</button>
                        </form>
                    </div>',
                        $historyUrl,
                        $this->generateUrl('app_market_watch_new', ['itemId' => $itemData['item']->getId()]),
                        $this->generateUrl('app_market_watch_delete_all_for_item', ['itemId' => $itemData['item']->getId()])
                    );
                }

                return $row;
            }, $pagedItemsData);

            // Générer les cartes mobiles HTML (utiliser la carte mobile existante)
            $mobileCards = '';
            foreach ($pagedItemsData as $itemData) {
                $mobileCards .= $this->renderView('market_watch/_mobile_card.html.twig', ['item' => $itemData]);
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
        Request $request,
        int $itemId,
        MarketWatchService $marketWatchService,
        ProfileCharacterService $profileCharacterService,
        ChartDataService $chartDataService,
        ItemRepository $itemRepository
    ): Response {
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $selectedCharacter = null;

        // Récupérer le filtre de période (défaut: all pour voir tout l'historique)
        $period = $request->query->get('period', 'all');
        if ($period !== 'all') {
            $period = preg_replace('/[^0-9]/', '', $period) ?: 'all';
        }

        // Mode admin : récupérer l'historique global
        if ($isAdmin) {
            $priceHistory = $marketWatchService->getGlobalPriceHistoryForItem($itemId, $period);
        } else {
            $result = $this->getSelectedCharacterOrRedirect($profileCharacterService, 'Aucun personnage sélectionné.');
            if ($result instanceof Response) {
                return $result;
            }
            $selectedCharacter = $result;
            $priceHistory = $marketWatchService->getPriceHistoryForItem($selectedCharacter, $itemId, $period);
        }

        // Récupérer l'item même si pas d'historique (pour afficher le nom)
        $item = !empty($priceHistory) ? $priceHistory[0]->getItem() : $itemRepository->find($itemId);

        if (!$item) {
            $this->addFlash('warning', 'Item introuvable.');
            return $this->redirectToRoute('app_market_watch_index');
        }

        $averages = $marketWatchService->calculatePriceAverages($priceHistory);
        $chartData = $chartDataService->prepareMarketWatchChartData($priceHistory, 'all');

        // En mode admin, compter le nombre de joueurs uniques
        $playersCount = 0;
        if ($isAdmin && !empty($priceHistory)) {
            $uniquePlayers = [];
            foreach ($priceHistory as $obs) {
                $uniquePlayers[$obs->getDofusCharacter()->getId()] = true;
            }
            $playersCount = count($uniquePlayers);
        }

        return $this->render('market_watch/history.html.twig', [
            'item' => $item,
            'price_history' => $priceHistory,
            'character' => $selectedCharacter,
            'averages' => $averages,
            'chart_data' => $chartData,
            'is_admin_view' => $isAdmin,
            'players_count' => $playersCount,
            'current_period' => $period,
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