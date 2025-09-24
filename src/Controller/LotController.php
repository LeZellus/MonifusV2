<?php

namespace App\Controller;

use App\Entity\LotGroup;
use App\Entity\Item;
use App\Entity\DofusCharacter;
use App\Form\LotGroupType;
use App\Repository\LotGroupRepository;
use App\Service\ProfileCharacterService;
use App\Service\LotManagementService;
use App\Trait\CharacterSelectionTrait;
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
    use CharacterSelectionTrait;
    #[Route('/', name: 'app_lot_index')]
    public function index(
        LotManagementService $lotManagementService,
        ProfileCharacterService $profileCharacterService
    ): Response {
        [$selectedCharacter, $characters] = $this->getCharacterData($profileCharacterService);
        $lots = $lotManagementService->getAvailableLotsForCharacter($selectedCharacter);

        return $this->render('lot/index.html.twig', [
            'lots' => $lots,
            'characters' => $characters,
        ]);
    }

    #[Route('/search', name: 'app_lot_search', methods: ['GET'])]
    public function search(
        LotManagementService $lotManagementService,
        ProfileCharacterService $profileCharacterService,
        Request $request
    ): JsonResponse {
        $selectedCharacter = $profileCharacterService->getSelectedCharacter($this->getUser());

        if (!$selectedCharacter) {
            return $this->createCharacterErrorResponse();
        }

        $searchQuery = trim($request->query->get('q', ''));
        $lots = $lotManagementService->searchLotsByItemName($selectedCharacter, $searchQuery);

        $tableRows = '';
        $mobileCards = '';

        foreach ($lots as $lot) {
            $tableRows .= $this->renderView('lot/_table_row.html.twig', ['item' => $lot]);
            $mobileCards .= $this->renderView('lot/_mobile_card.html.twig', ['item' => $lot]);
        }

        return new JsonResponse([
            'table_rows' => $tableRows,
            'mobile_cards' => $mobileCards,
            'count' => count($lots),
            'query' => $searchQuery
        ]);
    }

    #[Route('/new', name: 'app_lot_new')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        LotManagementService $lotManagementService,
        ProfileCharacterService $profileCharacterService
    ): Response {
        $result = $this->getSelectedCharacterOrRedirect($profileCharacterService, 'Aucun personnage sélectionné.');
        if ($result instanceof Response) {
            return $result;
        }
        $selectedCharacter = $result;

        $lotGroup = new LotGroup();
        $form = $this->createForm(LotGroupType::class, $lotGroup, ['is_edit' => false]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $itemId = $form->get('item')->getData();

            if ($itemId) {
                $item = $em->getRepository(Item::class)->find($itemId);
                if ($item) {
                    $lotGroup->setItem($item);
                    $lotManagementService->createLot($lotGroup, $selectedCharacter);
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
    public function edit(Request $request, LotGroup $lotGroup, EntityManagerInterface $em, CacheInvalidationService $cacheInvalidation): Response
    {
        $form = $this->createForm(LotGroupType::class, $lotGroup, [
            'is_edit' => true,
            'current_item' => $lotGroup->getItem()
        ]);
        
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            // Invalider le cache des stats utilisateur
            $cacheInvalidation->invalidateUserStatsAndMarkActivity($this->getUser());

            $this->addFlash('success', 'Lot modifié avec succès !');
            return $this->redirectToRoute('app_lot_index');
        }

        return $this->render('lot/edit.html.twig', [
            'lot' => $lotGroup,  // ✅ Changé de 'lot_group' vers 'lot'
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_lot_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        LotGroup $lotGroup,
        EntityManagerInterface $em,
        ProfileSelectorService $profileSelectorService,
        CacheInvalidationService $cacheInvalidation
    ): Response {
        if ($this->isCsrfTokenValid('delete'.$lotGroup->getId(), $request->getPayload()->getString('_token'))) {
            $em->remove($lotGroup);
            $em->flush();

            // Invalider le cache des compteurs pour mise à jour immédiate
            $profileSelectorService->forceInvalidateCountsCache($this->getUser());

            // Invalider le cache des stats utilisateur
            $cacheInvalidation->invalidateUserStatsAndMarkActivity($this->getUser());

            $this->addFlash('success', 'Lot supprimé avec succès !');
        }

        return $this->redirectToRoute('app_lot_index');
    }
}