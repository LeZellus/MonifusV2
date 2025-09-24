<?php

namespace App\Controller;

use App\Entity\MarketWatch;
use App\Entity\DofusCharacter;
use App\Form\MarketWatchType;
use App\Service\ProfileCharacterService;
use App\Service\MarketWatchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Repository\ItemRepository;
use App\Service\ChartDataService;

#[Route('/market-watch')]
#[IsGranted('ROLE_USER')]
class MarketWatchController extends AbstractController
{
    #[Route('/', name: 'app_market_watch_index')]
    public function index(
        MarketWatchService $marketWatchService,
        ProfileCharacterService $profileCharacterService
    ): Response {
        $selectedCharacter = $profileCharacterService->getSelectedCharacter($this->getUser());
        $characters = $profileCharacterService->getUserCharacters($this->getUser());
        $itemsData = $marketWatchService->getItemsDataForCharacter($selectedCharacter);

        return $this->render('market_watch/index.html.twig', [
            'items_data' => $itemsData,
            'characters' => $characters,
        ]);
    }

    
    #[Route('/new/{itemId}', name: 'app_market_watch_new', requirements: ['itemId' => '\d+'], defaults: ['itemId' => null])]
    public function new(
        Request $request,
        ItemRepository $itemRepository,
        MarketWatchService $marketWatchService,
        ProfileCharacterService $profileCharacterService,
        ?int $itemId = null
    ): Response {
        $selectedCharacter = $profileCharacterService->getSelectedCharacter($this->getUser());

        if (!$selectedCharacter) {
            $this->addFlash('warning', 'Créez d\'abord un personnage.');
            return $this->redirectToRoute('app_profile_index');
        }

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

        return $this->render('market_watch/new.html.twig', [
            'form' => $form,
            'character' => $selectedCharacter,
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
        $selectedCharacter = $profileCharacterService->getSelectedCharacter($this->getUser());

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

        return $this->render('market_watch/edit.html.twig', [
            'form' => $form,
            'market_watch' => $marketWatch,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_market_watch_delete', methods: ['POST'])]
    public function delete(
        MarketWatch $marketWatch,
        MarketWatchService $marketWatchService,
        ProfileCharacterService $profileCharacterService
    ): Response {
        $selectedCharacter = $profileCharacterService->getSelectedCharacter($this->getUser());

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
        $selectedCharacter = $profileCharacterService->getSelectedCharacter($this->getUser());

        if (!$selectedCharacter) {
            $this->addFlash('error', 'Aucun personnage sélectionné.');
            return $this->redirectToRoute('app_market_watch_index');
        }

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
        $selectedCharacter = $profileCharacterService->getSelectedCharacter($this->getUser());

        if (!$selectedCharacter) {
            $this->addFlash('error', 'Aucun personnage sélectionné.');
            return $this->redirectToRoute('app_market_watch_index');
        }

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
            return new JsonResponse(['error' => 'Aucun personnage sélectionné'], 400);
        }

        $searchQuery = trim($request->query->get('q', ''));
        $itemsData = $marketWatchService->getItemsDataForCharacter($selectedCharacter, $searchQuery);

        $tableRows = '';
        $mobileCards = '';

        foreach ($itemsData as $item) {
            $tableRows .= $this->renderView('market_watch/_table_row.html.twig', ['item' => $item]);
            $mobileCards .= $this->renderView('market_watch/_mobile_card.html.twig', ['item' => $item]);
        }

        return new JsonResponse([
            'table_rows' => $tableRows,
            'mobile_cards' => $mobileCards,
            'count' => count($itemsData),
            'query' => $searchQuery
        ]);
    }

}