<?php

namespace App\Controller;

use App\Entity\LotGroup;
use App\Entity\Item;
use App\Entity\DofusCharacter;
use App\Form\LotGroupType;
use App\Repository\LotGroupRepository;
use App\Service\ProfileCharacterService;
use App\Service\LotManagementService;
use App\Service\CacheInvalidationService;
use App\Trait\CharacterSelectionTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

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

        return $this->render('lot/index_custom.html.twig', [
            'character' => $selectedCharacter,
            'characters' => $characters,
        ]);
    }

    #[Route('/datatable', name: 'app_lot_datatable', methods: ['GET'])]
    public function datatable(
        Request $request,
        LotManagementService $lotManagementService,
        ProfileCharacterService $profileCharacterService,
        CsrfTokenManagerInterface $csrfTokenManager
    ): Response {
        error_log('🔍 Lot DataTable endpoint appelé avec: ' . json_encode($request->query->all()));

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

            // Récupérer les paramètres de la table personnalisée
            $page = max(1, (int) $request->query->get('page', 1));
            $length = (int) $request->query->get('length', 25);
            $search = $request->query->get('search', '');
            $sortColumn = (int) $request->query->get('sortColumn', 0);
            $sortDirection = $request->query->get('sortDirection', 'desc');

            error_log('📊 Paramètres: page=' . $page . ', length=' . $length . ', search=' . $search);

            // Récupérer tous les lots du personnage
            if (!empty($search)) {
                $allLots = $lotManagementService->searchLotsByItemName($selectedCharacter, $search);
            } else {
                $allLots = $lotManagementService->getAvailableLotsForCharacter($selectedCharacter);
            }

            error_log('📋 Nombre total de lots trouvés: ' . count($allLots));

            // Tri des lots
            $columns = ['item', 'configuration', 'buyPrice', 'sellPrice', 'profit', 'status'];
            if (isset($columns[$sortColumn])) {
                $sortKey = $columns[$sortColumn];
                usort($allLots, function($a, $b) use ($sortKey, $sortDirection) {
                    switch ($sortKey) {
                        case 'item':
                            $result = strcmp($a->getItem()->getName(), $b->getItem()->getName());
                            break;
                        case 'configuration':
                            $result = strcmp($a->getLotSize() . 'x ' . $a->getItem()->getName(), $b->getLotSize() . 'x ' . $b->getItem()->getName());
                            break;
                        case 'buyPrice':
                            $result = ($a->getBuyPricePerLot() ?? 0) <=> ($b->getBuyPricePerLot() ?? 0);
                            break;
                        case 'sellPrice':
                            $result = ($a->getSellPricePerLot() ?? 0) <=> ($b->getSellPricePerLot() ?? 0);
                            break;
                        case 'profit':
                            $profitA = ($a->getSellPricePerLot() ?? 0) - ($a->getBuyPricePerLot() ?? 0);
                            $profitB = ($b->getSellPricePerLot() ?? 0) - ($b->getBuyPricePerLot() ?? 0);
                            $result = $profitA <=> $profitB;
                            break;
                        case 'status':
                            $result = strcmp($a->getStatus()->value, $b->getStatus()->value);
                            break;
                        default:
                            $result = 0;
                    }
                    return $sortDirection === 'desc' ? -$result : $result;
                });
            }

            $totalRecords = count($allLots);

            // Pagination
            $start = ($page - 1) * $length;
            $pagedLots = array_slice($allLots, $start, $length);

            error_log('📄 Lots paginés: ' . count($pagedLots));

            // Formater les données avec HTML
            $formattedData = array_map(function($lot) use ($csrfTokenManager) {
                $profit = ($lot->getSellPricePerLot() ?? 0) - ($lot->getBuyPricePerLot() ?? 0);
                $profitPerUnit = $lot->getLotSize() > 0 ? $profit / $lot->getLotSize() : 0;

                return [
                    sprintf('<div class="flex items-center gap-2">
                        <img src="%s" alt="%s" class="w-8 h-8 rounded">
                        <span>%s</span>
                    </div>',
                        $lot->getItem()->getImgUrl() ?? '/images/items/default.png',
                        htmlspecialchars($lot->getItem()->getName()),
                        htmlspecialchars($lot->getItem()->getName())
                    ),
                    sprintf('<div class="text-center">
                        <div class="text-white font-medium">%dx</div>
                        <div class="text-gray-400 text-xs">%s</div>
                    </div>',
                        $lot->getLotSize(),
                        $lot->getItem()->getItemType() ? $lot->getItem()->getItemType()->value : 'N/A'
                    ),
                    sprintf('<span class="text-red-400">%s K</span>',
                        $lot->getBuyPricePerLot() ? number_format($lot->getBuyPricePerLot() / 1000, 1) : '-'
                    ),
                    sprintf('<span class="text-green-400">%s K</span>',
                        $lot->getSellPricePerLot() ? number_format($lot->getSellPricePerLot() / 1000, 1) : '-'
                    ),
                    sprintf('<div class="text-center">
                        <div class="%s font-medium">%s K</div>
                        <div class="text-gray-400 text-xs">Par unité: %s K</div>
                    </div>',
                        $profit >= 0 ? 'text-green-400' : 'text-red-400',
                        $profit ? number_format($profit / 1000, 1) : '-',
                        $profitPerUnit ? number_format($profitPerUnit / 1000, 1) : '-'
                    ),
                    sprintf('<span class="px-2 py-1 rounded-full text-xs %s">%s</span>',
                        $lot->getStatus()->value === 'available' ? 'bg-green-800 text-green-200' : 'bg-gray-800 text-gray-200',
                        $lot->getStatus()->value === 'available' ? 'Disponible' : ucfirst($lot->getStatus()->value)
                    ),
                    sprintf('<div class="flex gap-2">
                        <a href="%s" class="text-blue-400 hover:text-blue-300 text-xs px-2 py-1 border border-blue-400 rounded">Modifier</a>
                        <a href="%s" class="text-green-400 hover:text-green-300 text-xs px-2 py-1 border border-green-400 rounded">Vendre</a>
                        <form method="POST" action="%s" style="display:inline;" onsubmit="return confirm(\'Supprimer ce lot ?\')">
                            <input type="hidden" name="_token" value="%s">
                            <button type="submit" class="text-red-400 hover:text-red-300 text-xs px-2 py-1 border border-red-400 rounded">Supprimer</button>
                        </form>
                    </div>',
                        $this->generateUrl('app_lot_edit', ['id' => $lot->getId()]),
                        $this->generateUrl('app_lot_sell', ['id' => $lot->getId()]),
                        $this->generateUrl('app_lot_delete', ['id' => $lot->getId()]),
                        $csrfTokenManager->getToken('delete' . $lot->getId())->getValue()
                    )
                ];
            }, $pagedLots);

            // Générer les cartes mobiles HTML
            $mobileCards = '';
            foreach ($pagedLots as $lot) {
                $mobileCards .= $this->renderView('lot/_mobile_card.html.twig', ['item' => $lot]);
            }

            return new JsonResponse([
                'draw' => $page,
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $totalRecords,
                'data' => $formattedData,
                'mobile_cards' => $mobileCards
            ]);

        } catch (\Exception $e) {
            error_log('💥 Erreur Lot DataTable: ' . $e->getMessage());
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

        [$selectedCharacter, $characters] = $this->getCharacterData($profileCharacterService);

        return $this->render('lot/new.html.twig', [
            'form' => $form,
            'character' => $selectedCharacter,
            'characters' => $characters,
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
    public function edit(
        Request $request,
        LotGroup $lotGroup,
        EntityManagerInterface $em,
        CacheInvalidationService $cacheInvalidation,
        ProfileCharacterService $profileCharacterService
    ): Response {
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

        [$selectedCharacter, $characters] = $this->getCharacterData($profileCharacterService);

        return $this->render('lot/edit.html.twig', [
            'lot' => $lotGroup,
            'form' => $form,
            'characters' => $characters,
        ]);
    }

    #[Route('/{id}', name: 'app_lot_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        LotGroup $lotGroup,
        EntityManagerInterface $em,
        ProfileCharacterService $profileCharacterService,
        CacheInvalidationService $cacheInvalidation
    ): Response {
        if ($this->isCsrfTokenValid('delete'.$lotGroup->getId(), $request->getPayload()->getString('_token'))) {
            $em->remove($lotGroup);
            $em->flush();

            // Invalider le cache des compteurs pour mise à jour immédiate
            $profileCharacterService->forceInvalidateCountsCache($this->getUser());

            // Invalider le cache des stats utilisateur
            $cacheInvalidation->invalidateUserStatsAndMarkActivity($this->getUser());

            $this->addFlash('success', 'Lot supprimé avec succès !');
        }

        return $this->redirectToRoute('app_lot_index');
    }
}