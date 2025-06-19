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

        $watchList = [];
        if ($selectedCharacter) {
            $watchList = $marketWatchRepository->createQueryBuilder('mw')
                ->where('mw.dofusCharacter = :character')
                ->setParameter('character', $selectedCharacter)
                ->orderBy('mw.updatedAt', 'DESC')
                ->getQuery()
                ->getResult();
        }

        return $this->render('market_watch/index.html.twig', [
            'watch_list' => $watchList,
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

            $this->addFlash('success', 'Surveillance ajoutée avec succès !');
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

        // Vérifier que la surveillance appartient au personnage sélectionné
        if ($marketWatch->getDofusCharacter() !== $selectedCharacter) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(MarketWatchType::class, $marketWatch);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Surveillance modifiée avec succès !');
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

        // Vérifier que la surveillance appartient au personnage sélectionné
        if ($marketWatch->getDofusCharacter() !== $selectedCharacter) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($marketWatch);
        $em->flush();

        $this->addFlash('success', 'Surveillance supprimée avec succès !');
        return $this->redirectToRoute('app_market_watch_index');
    }
}