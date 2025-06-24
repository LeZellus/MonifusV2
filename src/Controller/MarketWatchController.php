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
        if ($form->isSubmitted() && $form->isValid()) {
            $itemId = $form->get('item')->getData();
            if ($itemId && $item = $itemRepository->find($itemId)) {
                $marketWatch->setItem($item);
                $marketWatch->setDofusCharacter($selectedCharacter);
                
                $em->persist($marketWatch);
                $em->flush();

                $this->addFlash('success', 'Observation de prix ajoutée avec succès !');
                return $this->redirectToRoute('app_market_watch_index');
            }
            
            $this->addFlash('error', 'Veuillez sélectionner une ressource valide.');
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
        ChartDataService $chartDataService // Injection du service
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
        
        // Utilisation du service pour les données du graphique
        $chartData = $chartDataService->prepareMarketWatchChartData($priceHistory);

        return $this->render('market_watch/history.html.twig', [
            'item' => $item,
            'price_history' => $priceHistory,
            'character' => $selectedCharacter,
            'averages' => $averages,
            'chart_data' => $chartData,
        ]);
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