<?php

namespace App\Controller;

use App\Entity\LotGroup;
use App\Form\LotGroupType;
use App\Repository\LotGroupRepository;
use App\Service\CharacterSelectionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
            $lots = $lotRepository->createQueryBuilder('lg')
                ->where('lg.dofusCharacter = :character')
                ->setParameter('character', $selectedCharacter)
                ->orderBy('lg.createdAt', 'DESC')
                ->getQuery()
                ->getResult();
        }

        return $this->render('lot/index.html.twig', [
            'lots' => $lots,
            'characters' => $characters,
            'selectedCharacter' => $selectedCharacter,
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
            $this->addFlash('error', 'Aucun personnage sélectionné. Créez d\'abord un personnage.');
            return $this->redirectToRoute('app_profile_index');
        }

        $lotGroup = new LotGroup();
        $form = $this->createForm(LotGroupType::class, $lotGroup);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $lotGroup->setDofusCharacter($selectedCharacter);
            $em->persist($lotGroup);
            $em->flush();

            $this->addFlash('success', 'Lot ajouté avec succès !');
            return $this->redirectToRoute('app_lot_index');
        }

        return $this->render('lot/new.html.twig', [
            'form' => $form,
            'character' => $selectedCharacter,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_lot_edit')]
    public function edit(
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

        $form = $this->createForm(LotGroupType::class, $lotGroup);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Lot modifié avec succès !');
            return $this->redirectToRoute('app_lot_index');
        }

        return $this->render('lot/edit.html.twig', [
            'form' => $form,
            'lot' => $lotGroup,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_lot_delete', methods: ['POST'])]
    public function delete(
        LotGroup $lotGroup, 
        EntityManagerInterface $em,
        CharacterSelectionService $characterService
    ): Response {
        $selectedCharacter = $characterService->getSelectedCharacter($this->getUser());

        // Vérifier que le lot appartient au personnage sélectionné
        if ($lotGroup->getDofusCharacter() !== $selectedCharacter) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($lotGroup);
        $em->flush();

        $this->addFlash('success', 'Lot supprimé avec succès !');
        return $this->redirectToRoute('app_lot_index');
    }
}