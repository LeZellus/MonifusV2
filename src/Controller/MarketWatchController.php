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

        $itemsData = [];
        if ($selectedCharacter) {
            // Récupérer toutes les observations pour ce personnage
            $observations = $marketWatchRepository->createQueryBuilder('mw')
                ->select('mw', 'i')
                ->join('mw.item', 'i')
                ->where('mw.dofusCharacter = :character')
                ->setParameter('character', $selectedCharacter)
                ->orderBy('mw.observedAt', 'DESC')
                ->getQuery()
                ->getResult();

            // Grouper par item et calculer les moyennes
            $itemsGrouped = [];
            foreach ($observations as $observation) {
                $itemId = $observation->getItem()->getId();
                
                if (!isset($itemsGrouped[$itemId])) {
                    $itemsGrouped[$itemId] = [
                        'item' => $observation->getItem(),
                        'observations' => [],
                        'latest_date' => $observation->getObservedAt(),
                        'oldest_date' => $observation->getObservedAt()
                    ];
                }
                
                $itemsGrouped[$itemId]['observations'][] = $observation;
                
                // Mettre à jour les dates
                if ($observation->getObservedAt() > $itemsGrouped[$itemId]['latest_date']) {
                    $itemsGrouped[$itemId]['latest_date'] = $observation->getObservedAt();
                }
                if ($observation->getObservedAt() < $itemsGrouped[$itemId]['oldest_date']) {
                    $itemsGrouped[$itemId]['oldest_date'] = $observation->getObservedAt();
                }
            }

            // Calculer les moyennes pour chaque item
            foreach ($itemsGrouped as $itemId => $data) {
                $pricesUnit = [];
                $prices10 = [];
                $prices100 = [];
                
                foreach ($data['observations'] as $obs) {
                    if ($obs->getPricePerUnit() !== null) {
                        $pricesUnit[] = $obs->getPricePerUnit();
                    }
                    if ($obs->getPricePer10() !== null) {
                        $prices10[] = $obs->getPricePer10();
                    }
                    if ($obs->getPricePer100() !== null) {
                        $prices100[] = $obs->getPricePer100();
                    }
                }
                
                $trackingPeriod = $data['latest_date']->diff($data['oldest_date'])->days;
                
                $itemsData[] = [
                    'item' => $data['item'],
                    'observation_count' => count($data['observations']),
                    'latest_date' => $data['latest_date'],
                    'tracking_period_days' => $trackingPeriod,
                    'avg_price_unit' => !empty($pricesUnit) ? round(array_sum($pricesUnit) / count($pricesUnit)) : null,
                    'avg_price_10' => !empty($prices10) ? round(array_sum($prices10) / count($prices10)) : null,
                    'avg_price_100' => !empty($prices100) ? round(array_sum($prices100) / count($prices100)) : null,
                    'price_unit_count' => count($pricesUnit),
                    'price_10_count' => count($prices10),
                    'price_100_count' => count($prices100)
                ];
            }

            // Trier par date de dernière observation (plus récent en premier)
            usort($itemsData, function($a, $b) {
                return $b['latest_date'] <=> $a['latest_date'];
            });
        }

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
        CharacterSelectionService $characterService
    ): Response {
        $selectedCharacter = $characterService->getSelectedCharacter($this->getUser());
        
        if (!$selectedCharacter) {
            $this->addFlash('error', 'Aucun personnage sélectionné. Créez d\'abord un personnage.');
            return $this->redirectToRoute('app_profile_index');
        }

        $marketWatch = new MarketWatch();
        $form = $this->createForm(MarketWatchType::class, $marketWatch);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $marketWatch->setDofusCharacter($selectedCharacter);
            $em->persist($marketWatch);
            $em->flush();

            $this->addFlash('success', 'Observation de prix ajoutée avec succès !');
            return $this->redirectToRoute('app_market_watch_index');
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

        // Vérifier que l'observation appartient au personnage sélectionné
        if ($marketWatch->getDofusCharacter() !== $selectedCharacter) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(MarketWatchType::class, $marketWatch);

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

        // Vérifier que l'observation appartient au personnage sélectionné
        if ($marketWatch->getDofusCharacter() !== $selectedCharacter) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($marketWatch);
        $em->flush();

        $this->addFlash('success', 'Observation supprimée avec succès !');
        return $this->redirectToRoute('app_market_watch_index');
    }

    #[Route('/item/{itemId}/history', name: 'app_market_watch_history')]
    public function itemHistory(
        int $itemId,
        MarketWatchRepository $marketWatchRepository,
        CharacterSelectionService $characterService
    ): Response {
        $selectedCharacter = $characterService->getSelectedCharacter($this->getUser());
        
        if (!$selectedCharacter) {
            $this->addFlash('error', 'Aucun personnage sélectionné.');
            return $this->redirectToRoute('app_market_watch_index');
        }

        // Récupérer l'historique des prix pour cet item
        $priceHistory = $marketWatchRepository->createQueryBuilder('mw')
            ->where('mw.dofusCharacter = :character')
            ->andWhere('mw.item = :itemId')
            ->setParameter('character', $selectedCharacter)
            ->setParameter('itemId', $itemId)
            ->orderBy('mw.observedAt', 'DESC')
            ->getQuery()
            ->getResult();

        if (empty($priceHistory)) {
            $this->addFlash('warning', 'Aucun historique de prix pour cet item.');
            return $this->redirectToRoute('app_market_watch_index');
        }

        $item = $priceHistory[0]->getItem();

        // Calculer les moyennes pour affichage
        $pricesUnit = [];
        $prices10 = [];
        $prices100 = [];
        
        foreach ($priceHistory as $obs) {
            if ($obs->getPricePerUnit() !== null) {
                $pricesUnit[] = $obs->getPricePerUnit();
            }
            if ($obs->getPricePer10() !== null) {
                $prices10[] = $obs->getPricePer10();
            }
            if ($obs->getPricePer100() !== null) {
                $prices100[] = $obs->getPricePer100();
            }
        }

        $averages = [
            'avg_price_unit' => !empty($pricesUnit) ? round(array_sum($pricesUnit) / count($pricesUnit)) : null,
            'avg_price_10' => !empty($prices10) ? round(array_sum($prices10) / count($prices10)) : null,
            'avg_price_100' => !empty($prices100) ? round(array_sum($prices100) / count($prices100)) : null,
            'price_unit_count' => count($pricesUnit),
            'price_10_count' => count($prices10),
            'price_100_count' => count($prices100)
        ];

        return $this->render('market_watch/history.html.twig', [
            'item' => $item,
            'price_history' => $priceHistory,
            'character' => $selectedCharacter,
            'averages' => $averages,
        ]);
    }
}