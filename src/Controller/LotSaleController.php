<?php

namespace App\Controller;

use App\Entity\LotGroup;
use App\Entity\LotUnit;
use App\Form\LotUnitType;
use App\Enum\LotStatus;
use App\Service\CharacterSelectionService;
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
        CharacterSelectionService $characterService
    ): Response {
        $selectedCharacter = $characterService->getSelectedCharacter($this->getUser());

        // Vérifications de sécurité
        if ($lotGroup->getDofusCharacter() !== $selectedCharacter) {
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
}