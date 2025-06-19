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

        // Vérifier que le lot appartient au personnage sélectionné
        if ($lotGroup->getDofusCharacter() !== $selectedCharacter) {
            throw $this->createAccessDeniedException();
        }

        // Vérifier que le lot est disponible
        if ($lotGroup->getStatus() !== LotStatus::AVAILABLE) {
            $this->addFlash('error', 'Ce lot n\'est pas disponible à la vente.');
            return $this->redirectToRoute('app_lot_index');
        }

        $lotUnit = new LotUnit();
        $lotUnit->setLotGroup($lotGroup);
        $lotUnit->setSoldAt(new \DateTime());
        
        // Préremplir avec le prix de vente prévu
        $lotUnit->setActualSellPrice($lotGroup->getSellPricePerLot());

        $form = $this->createForm(LotUnitType::class, $lotUnit);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Marquer le lot comme vendu
            $lotGroup->setStatus(LotStatus::SOLD);
            
            $em->persist($lotUnit);
            $em->flush();

            $this->addFlash('success', 'Lot vendu avec succès !');
            return $this->redirectToRoute('app_lot_index');
        }

        return $this->render('lot_sale/sell.html.twig', [
            'form' => $form,
            'lot_group' => $lotGroup,
        ]);
    }
}