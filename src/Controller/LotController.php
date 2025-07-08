<?php

namespace App\Controller;

use App\Entity\LotGroup;
use App\Entity\Item;
use App\Form\LotGroupType;
use App\Repository\LotGroupRepository;
use App\Service\CharacterSelectionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/lot')]
#[IsGranted('ROLE_USER')]
class LotController extends AbstractController
{
    #[Route('/', name: 'app_lot_index')]
    public function index(
        LotGroupRepository $lotRepository,
        CharacterSelectionService $characterService
    ): Response {
        $selectedCharacter = $characterService->getSelectedCharacter($this->getUser());
        $characters = $characterService->getUserCharacters($this->getUser());

        $lots = [];
        if ($selectedCharacter) {
            $lots = $lotRepository->findAvailableByCharacter($selectedCharacter);
        }

        return $this->render('lot/index.html.twig', [
            'lots' => $lots,
            'characters' => $characters,
            'selectedCharacter' => $selectedCharacter,
        ]);
    }

    #[Route('/search', name: 'app_lot_search', methods: ['GET'])]
    public function search(
        LotGroupRepository $lotRepository,
        CharacterSelectionService $characterService,
        Request $request
    ): JsonResponse {
        $selectedCharacter = $characterService->getSelectedCharacter($this->getUser());
        
        if (!$selectedCharacter) {
            return new JsonResponse(['error' => 'Aucun personnage sélectionné'], 400);
        }

        $searchQuery = trim($request->query->get('q', ''));
        
        // Rechercher les lots par nom d'item
        $lots = $lotRepository->searchByItemName($selectedCharacter, $searchQuery);

        // Rendu des lignes du tableau (clé: table_rows)
        $tableRows = '';
        foreach ($lots as $lot) {
            $tableRows .= $this->renderView('lot/_table_row.html.twig', [
                'item' => $lot
            ]);
        }

        // Rendu des cartes mobile (clé: mobile_cards)
        $mobileCards = '';
        foreach ($lots as $lot) {
            $mobileCards .= $this->renderView('lot/_mobile_card.html.twig', [
                'item' => $lot
            ]);
        }

        return new JsonResponse([
            'table_rows' => $tableRows,    // ✅ Correspond à data-search-container="table_rows"
            'mobile_cards' => $mobileCards, // ✅ Correspond à data-search-container="mobile_cards"
            'count' => count($lots),
            'query' => $searchQuery
        ]);
    }

    #[Route('/new', name: 'app_lot_new')]
    public function new(
        Request $request, 
        EntityManagerInterface $em, 
        CharacterSelectionService $characterService
    ): Response {
        $selectedCharacter = $characterService->getSelectedCharacter($this->getUser());
        
        if (!$selectedCharacter) {
            $this->addFlash('error', 'Aucun personnage sélectionné.');
            return $this->redirectToRoute('app_profile_index');
        }

        $lotGroup = new LotGroup();
        $form = $this->createForm(LotGroupType::class, $lotGroup, ['is_edit' => false]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $itemId = $form->get('item')->getData();
            
            if ($itemId) {
                $item = $em->getRepository(Item::class)->find($itemId);
                if ($item) {
                    $lotGroup->setItem($item);
                    $lotGroup->setDofusCharacter($selectedCharacter);
                    
                    $em->persist($lotGroup);
                    $em->flush();

                    $this->addFlash('success', 'Lot ajouté avec succès !');
                    return $this->redirectToRoute('app_lot_index');
                }
            }
            
            $this->addFlash('error', 'Veuillez sélectionner un item valide.');
        }

        return $this->render('lot/new.html.twig', [
            'form' => $form,
            'character' => $selectedCharacter,
        ]);
    }

    #[Route('/{id}', name: 'app_lot_show', methods: ['GET'])]
    public function show(LotGroup $lotGroup): Response
    {
        return $this->render('lot/show.html.twig', [
            'lot_group' => $lotGroup,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_lot_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, LotGroup $lotGroup, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(LotGroupType::class, $lotGroup, [
            'is_edit' => true,
            'current_item' => $lotGroup->getItem()
        ]);
        
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Lot modifié avec succès !');
            return $this->redirectToRoute('app_lot_index');
        }

        return $this->render('lot/edit.html.twig', [
            'lot' => $lotGroup,  // ✅ Changé de 'lot_group' vers 'lot'
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_lot_delete', methods: ['POST'])]
    public function delete(Request $request, LotGroup $lotGroup, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$lotGroup->getId(), $request->getPayload()->getString('_token'))) {
            $em->remove($lotGroup);
            $em->flush();
            $this->addFlash('success', 'Lot supprimé avec succès !');
        }

        return $this->redirectToRoute('app_lot_index');
    }
}