<?php

namespace App\Controller;

use App\Entity\MarketWatch;
use App\Form\MarketWatchType;
use App\Repository\MarketWatchRepository;
use App\Service\CharacterSelectionService;
use Doctrine\ORM\EntityManagerInterface;
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
        MarketWatchRepository $marketWatchRepository,
        CharacterSelectionService $characterService
    ): Response {
        $selectedCharacter = $characterService->getSelectedCharacter($this->getUser());
        $characters = $characterService->getUserCharacters($this->getUser());

        // Une seule ligne pour récupérer toutes les données avec stats
        $itemsData = $selectedCharacter 
            ? $marketWatchRepository->getItemsDataWithStats($selectedCharacter)
            : [];

        return $this->render('market_watch/index.html.twig', [
            'items_data' => $itemsData,
            'characters' => $characters,
            'selectedCharacter' => $selectedCharacter,
        ]);
    }

    #[Route('/new', name: 'app_market_watch_new')]
    public function new(
        Request $request, 
        EntityManagerInterface $em,
        ItemRepository $itemRepository,
        CharacterSelectionService $characterService
    ): Response {
        $selectedCharacter = $characterService->getSelectedCharacter($this->getUser());

        if (!$selectedCharacter) {
            $this->addFlash('warning', 'Créez d\'abord un personnage.');
            return $this->redirectToRoute('app_profile_index');
        }

        $marketWatch = new MarketWatch();
        $form = $this->createForm(MarketWatchType::class, $marketWatch, ['is_edit' => false]);

        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            // DEBUG : Afficher les données du formulaire
            $formData = $request->request->all();
            error_log("=== DEBUG FORM DATA ===");
            error_log("Form submitted: " . json_encode($formData, JSON_PRETTY_PRINT));
            
            // DEBUG : Vérifier les données de l'entité
            error_log("=== DEBUG ENTITY DATA ===");
            error_log("PricePerUnit: " . ($marketWatch->getPricePerUnit() ?? 'null'));
            error_log("PricePer10: " . ($marketWatch->getPricePer10() ?? 'null'));
            error_log("PricePer100: " . ($marketWatch->getPricePer100() ?? 'null'));
            error_log("PricePer1000: " . ($marketWatch->getPricePer1000() ?? 'null'));
            
            // DEBUG : Vérifier les erreurs de validation
            if (!$form->isValid()) {
                error_log("=== FORM ERRORS ===");
                foreach ($form->getErrors(true) as $error) {
                    error_log("Error: " . $error->getMessage());
                }
            }
            
            if ($form->isValid()) {
                $itemId = $form->get('item')->getData();
                if ($itemId && $item = $itemRepository->find($itemId)) {
                    $marketWatch->setItem($item);
                    $marketWatch->setDofusCharacter($selectedCharacter);
                    
                    // DEBUG : Vérifier les données avant persist
                    error_log("=== DEBUG BEFORE PERSIST ===");
                    error_log("Item: " . $item->getName());
                    error_log("Character: " . $selectedCharacter->getName());
                    error_log("PricePer1000 avant persist: " . ($marketWatch->getPricePer1000() ?? 'null'));
                    
                    $em->persist($marketWatch);
                    $em->flush();

                    $this->addFlash('success', 'Observation de prix ajoutée avec succès !');
                    return $this->redirectToRoute('app_market_watch_index');
                }
                
                $this->addFlash('error', 'Veuillez sélectionner une ressource valide.');
            } else {
                $this->addFlash('error', 'Le formulaire contient des erreurs.');
            }
        }

        return $this->render('market_watch/new.html.twig', [
            'form' => $form,
            'character' => $selectedCharacter,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_market_watch_edit')]
    public function edit(
        MarketWatch $marketWatch, 
        Request $request, 
        EntityManagerInterface $em,
        CharacterSelectionService $characterService
    ): Response {
        $selectedCharacter = $characterService->getSelectedCharacter($this->getUser());

        if ($marketWatch->getDofusCharacter() !== $selectedCharacter) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(MarketWatchType::class, $marketWatch, ['is_edit' => true]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
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
        EntityManagerInterface $em,
        CharacterSelectionService $characterService
    ): Response {
        $selectedCharacter = $characterService->getSelectedCharacter($this->getUser());

        if ($marketWatch->getDofusCharacter() !== $selectedCharacter) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($marketWatch);
        $em->flush();

        $this->addFlash('success', 'Observation supprimée avec succès !');
        return $this->redirectToRoute('app_market_watch_index');
    }

    #[Route('/item/{itemId}/delete-all', name: 'app_market_watch_delete_all_for_item', methods: ['POST'])]
    public function deleteAllForItem(
        int $itemId,
        MarketWatchRepository $marketWatchRepository,
        CharacterSelectionService $characterService,
        EntityManagerInterface $em
    ): Response {
        $selectedCharacter = $characterService->getSelectedCharacter($this->getUser());
        
        if (!$selectedCharacter) {
            $this->addFlash('error', 'Aucun personnage sélectionné.');
            return $this->redirectToRoute('app_market_watch_index');
        }

        $observations = $marketWatchRepository->findPriceHistoryForItem($selectedCharacter, $itemId);
        
        if (empty($observations)) {
            $this->addFlash('warning', 'Aucune observation à supprimer.');
            return $this->redirectToRoute('app_market_watch_index');
        }

        foreach ($observations as $observation) {
            $em->remove($observation);
        }
        $em->flush();

        $this->addFlash('success', count($observations) . ' observation(s) supprimée(s) pour ' . $observations[0]->getItem()->getName());
        return $this->redirectToRoute('app_market_watch_index');
    }

    #[Route('/item/{itemId}/history', name: 'app_market_watch_history')]
    public function itemHistory(
        int $itemId,
        MarketWatchRepository $marketWatchRepository,
        CharacterSelectionService $characterService,
        ChartDataService $chartDataService
    ): Response {
        $selectedCharacter = $characterService->getSelectedCharacter($this->getUser());
        
        if (!$selectedCharacter) {
            $this->addFlash('error', 'Aucun personnage sélectionné.');
            return $this->redirectToRoute('app_market_watch_index');
        }

        $priceHistory = $marketWatchRepository->findPriceHistoryForItem($selectedCharacter, $itemId);

        if (empty($priceHistory)) {
            $this->addFlash('warning', 'Aucun historique de prix pour cet item.');
            return $this->redirectToRoute('app_market_watch_index');
        }

        $item = $priceHistory[0]->getItem();
        $averages = $marketWatchRepository->calculatePriceAverages($priceHistory);
        
        // Génération des données COMPLÈTES (pas de filtrage côté PHP)
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
        MarketWatchRepository $marketWatchRepository,
        CharacterSelectionService $characterService,
        Request $request
    ): JsonResponse {
        // Debug pour voir si la route est appelée
        error_log("🔍 Route de recherche appelée");
        error_log("Query parameter: " . $request->query->get('q', 'VIDE'));
        
        $selectedCharacter = $characterService->getSelectedCharacter($this->getUser());
        
        if (!$selectedCharacter) {
            error_log("❌ Aucun personnage sélectionné");
            return new JsonResponse(['error' => 'Aucun personnage sélectionné'], 400);
        }
        
        error_log("✅ Personnage trouvé: " . $selectedCharacter->getName());

        $searchQuery = trim($request->query->get('q', ''));
        error_log("Terme de recherche traité: '" . $searchQuery . "'");
        
        // Utiliser votre méthode existante avec la recherche
        $itemsData = $marketWatchRepository->getItemsDataWithStats($selectedCharacter, $searchQuery);
        error_log("Nombre d'items trouvés: " . count($itemsData));

        // Rendu des lignes du tableau
        $tableRows = '';
        foreach ($itemsData as $item) {
            $tableRows .= $this->renderView('market_watch/_table_row.html.twig', [
                'item' => $item
            ]);
        }
        error_log("HTML table rows généré, longueur: " . strlen($tableRows));

        // Rendu des cartes mobile
        $mobileCards = '';
        foreach ($itemsData as $item) {
            $mobileCards .= $this->renderView('market_watch/_mobile_card.html.twig', [
                'item' => $item
            ]);
        }
        error_log("HTML mobile cards généré, longueur: " . strlen($mobileCards));

        $response = [
            'table_rows' => $tableRows,
            'mobile_cards' => $mobileCards,
            'count' => count($itemsData),
            'query' => $searchQuery
        ];
        
        error_log("Réponse JSON prête: " . json_encode([
            'count' => $response['count'],
            'query' => $response['query'],
            'table_rows_length' => strlen($response['table_rows']),
            'mobile_cards_length' => strlen($response['mobile_cards'])
        ]));

        return new JsonResponse($response);
    }

    public function history(Item $item, ChartDataService $chartService): Response
    {
        $priceHistory = $this->marketWatchRepository
            ->findByItemOrderedByDate($item);
            
        $averages = $this->marketWatchRepository
            ->getAveragesByItem($item);

        // Générer les données complètes (tous types)
        $chartData = $chartService->buildChartData($priceHistory);

        return $this->render('market_watch/history.html.twig', [
            'item' => $item,
            'price_history' => $priceHistory,
            'averages' => $averages,
            'chart_data' => $chartData
        ]);
    }
}