<?php

namespace App\Controller;

use App\Entity\LotGroup;
use App\Entity\DofusCharacter;
use App\Form\LotGroupType;
use App\Repository\LotGroupRepository;
use App\Repository\DofusCharacterRepository;
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
        DofusCharacterRepository $characterRepository
    ): Response {
        // Récupérer tous les personnages de l'utilisateur
        $characters = $characterRepository->createQueryBuilder('c')
            ->join('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->setParameter('user', $this->getUser())
            ->getQuery()
            ->getResult();

        // Récupérer tous les lots des personnages de l'utilisateur
        $lots = [];
        if (!empty($characters)) {
            $lots = $lotRepository->createQueryBuilder('lg')
                ->join('lg.dofusCharacter', 'c')
                ->join('c.tradingProfile', 'tp')
                ->where('tp.user = :user')
                ->setParameter('user', $this->getUser())
                ->orderBy('lg.createdAt', 'DESC')
                ->getQuery()
                ->getResult();
        }

        return $this->render('lot/index.html.twig', [
            'lots' => $lots,
            'characters' => $characters,
        ]);
    }

    #[Route('/character/{id}/new', name: 'app_lot_new')]
    public function new(
        DofusCharacter $character, 
        Request $request, 
        EntityManagerInterface $em
    ): Response {
        // Vérifier que le personnage appartient à l'utilisateur
        if ($character->getTradingProfile()->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $lotGroup = new LotGroup();
        $form = $this->createForm(LotGroupType::class, $lotGroup);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $lotGroup->setDofusCharacter($character);
            $em->persist($lotGroup);
            $em->flush();

            $this->addFlash('success', 'Lot ajouté avec succès !');
            return $this->redirectToRoute('app_lot_index');
        }

        return $this->render('lot/new.html.twig', [
            'form' => $form,
            'character' => $character,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_lot_edit')]
    public function edit(
        LotGroup $lotGroup, 
        Request $request, 
        EntityManagerInterface $em
    ): Response {
        // Vérifier que le lot appartient à l'utilisateur
        if ($lotGroup->getDofusCharacter()->getTradingProfile()->getUser() !== $this->getUser()) {
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
        EntityManagerInterface $em
    ): Response {
        // Vérifier que le lot appartient à l'utilisateur
        if ($lotGroup->getDofusCharacter()->getTradingProfile()->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($lotGroup);
        $em->flush();

        $this->addFlash('success', 'Lot supprimé avec succès !');
        return $this->redirectToRoute('app_lot_index');
    }
}