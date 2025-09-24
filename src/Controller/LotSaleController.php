<?php

namespace App\Controller;

use App\Entity\LotGroup;
use App\Entity\LotUnit;
use App\Form\LotUnitType;
use App\Enum\LotStatus;
use App\Service\ProfileCharacterService;
use App\Service\CacheInvalidationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/lot-sale')]
#[IsGranted('ROLE_USER')]
class LotSaleController extends AbstractController
{
    #[Route('/{id}/sell', name: 'app_lot_sell')]
    public function sell(
        LotGroup $lotGroup,
        Request $request,
        EntityManagerInterface $em,
        ProfileCharacterService $profileCharacterService,
        CacheInvalidationService $cacheInvalidation
    ): Response {
        $selectedCharacter = $profileCharacterService->getSelectedCharacter($this->getUser());

        // Vérifications de sécurité
        if (!$selectedCharacter || $lotGroup->getDofusCharacter()->getId() !== $selectedCharacter->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($lotGroup->getStatus() !== LotStatus::AVAILABLE) {
            $this->addFlash('error', 'Ce lot n\'est pas disponible à la vente.');
            return $this->redirectToRoute('app_lot_index');
        }

        // Créer la vente
        $lotUnit = new LotUnit();
        $lotUnit->setLotGroup($lotGroup);
        $lotUnit->setSoldAt(new \DateTime());
        $defaultSellPrice = $lotGroup->getSellPricePerLot() ?? $lotGroup->getBuyPricePerLot();
        $lotUnit->setActualSellPrice($defaultSellPrice);

        // Formulaire avec quantité
        $form = $this->createForm(LotUnitType::class, $lotUnit, [
            'lot_group' => $lotGroup
        ]);

        // Pré-remplir la quantité avec tout le stock
        $form->get('quantitySold')->setData($lotGroup->getLotSize());

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $quantitySold = $form->get('quantitySold')->getData();
            $actualSellPrice = $lotUnit->getActualSellPrice();
            $remainingQuantity = $lotGroup->getLotSize() - $quantitySold;

            if ($remainingQuantity < 0) {
                $this->addFlash('error', 'Quantité vendue supérieure au stock disponible.');
                return $this->render('lot_sale/sell.html.twig', [
                    'form' => $form,
                    'lot_group' => $lotGroup,
                ]);
            }

            // ⚡ NOUVEAU : Mettre à jour le prix de vente du lot si pas encore défini
            if ($lotGroup->getSellPricePerLot() === null) {
                $lotGroup->setSellPricePerLot($actualSellPrice);
            }

            // Enregistrer la vente avec la quantité
            $lotUnit->setQuantitySold($quantitySold);
            $em->persist($lotUnit);

            if ($remainingQuantity === 0) {
                // Tout vendu = marquer le lot comme vendu
                $lotGroup->setStatus(LotStatus::SOLD);
            } else {
                // Vente partielle = réduire la quantité du lot
                $lotGroup->setLotSize($remainingQuantity);
            }

            $em->flush();

            // Invalider le cache des compteurs car le statut des lots a changé
            $profileCharacterService->forceInvalidateCountsCache($this->getUser());

            // Invalider le cache des stats utilisateur
            $cacheInvalidation->invalidateUserStatsAndMarkActivity($this->getUser());

            $message = $remainingQuantity === 0
                ? "Lot entièrement vendu !"
                : "Vente partielle effectuée ! Reste {$remainingQuantity} lots.";

            $this->addFlash('success', $message);
            return $this->redirectToRoute('app_lot_index');
        }

        return $this->render('lot_sale/sell.html.twig', [
            'form' => $form,
            'lot_group' => $lotGroup,
        ]);
    }

    #[Route('/{id}/cancel', name: 'app_lot_unit_cancel', methods: ['POST'])]
    public function cancelSale(
        int $id,
        EntityManagerInterface $em,
        ProfileCharacterService $profileCharacterService,
        CacheInvalidationService $cacheInvalidation
    ): Response {
        $selectedCharacter = $profileCharacterService->getSelectedCharacter($this->getUser());
        
        if (!$selectedCharacter) {
            throw $this->createAccessDeniedException();
        }

        // Récupérer le LotUnit à annuler
        $lotUnit = $em->getRepository(LotUnit::class)->find($id);
        
        if (!$lotUnit) {
            throw $this->createNotFoundException('Vente non trouvée.');
        }

        $lotGroup = $lotUnit->getLotGroup();
        
        // Vérification de sécurité
        if ($lotGroup->getDofusCharacter()->getId() !== $selectedCharacter->getId()) {
            throw $this->createAccessDeniedException();
        }

        // ✅ Restaurer la quantité dans le lot
        $quantityToRestore = $lotUnit->getQuantitySold();
        $lotGroup->setLotSize($lotGroup->getLotSize() + $quantityToRestore);
        
        // ✅ Si le lot était SOLD, le remettre AVAILABLE
        if ($lotGroup->getStatus() === LotStatus::SOLD) {
            $lotGroup->setStatus(LotStatus::AVAILABLE);
        }
        
        // ✅ Supprimer la vente
        $em->remove($lotUnit);
        $em->flush();

        // Invalider le cache des compteurs car le statut des lots a changé
        $profileCharacterService->forceInvalidateCountsCache($this->getUser());

        // Invalider le cache des stats utilisateur
        $cacheInvalidation->invalidateUserStatsAndMarkActivity($this->getUser());

        $this->addFlash('success', "Vente annulée ! {$quantityToRestore} lots restaurés.");
        return $this->redirectToRoute('app_lot_index');
    }
}