<?php

namespace App\Controller;

use App\Entity\MarketWatch;
use App\Entity\DofusCharacter;
use App\Form\MarketWatchType;
use App\Repository\MarketWatchRepository;
use App\Repository\DofusCharacterRepository;
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
        DofusCharacterRepository $characterRepository
    ): Response {
        // Récupérer tous les personnages de l'utilisateur
        $characters = $characterRepository->createQueryBuilder('c')
            ->join('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->setParameter('user', $this->getUser())
            ->getQuery()
            ->getResult();

        // Récupérer toutes les surveillances des personnages de l'utilisateur
        $watchList = [];
        if (!empty($characters)) {
            $watchList = $marketWatchRepository->createQueryBuilder('mw')
                ->join('mw.dofusCharacter', 'c')
                ->join('c.tradingProfile', 'tp')
                ->where('tp.user = :user')
                ->setParameter('user', $this->getUser())
                ->orderBy('mw.updatedAt', 'DESC')
                ->getQuery()
                ->getResult();
        }

        return $this->render('market_watch/index.html.twig', [
            'watch_list' => $watchList,
            'characters' => $characters,
        ]);
    }

    #[Route('/character/{id}/new', name: 'app_market_watch_new')]
    public function new(
        DofusCharacter $character, 
        Request $request, 
        EntityManagerInterface $em
    ): Response {
        // Vérifier que le personnage appartient à l'utilisateur
        if ($character->getTradingProfile()->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $marketWatch = new MarketWatch();
        $form = $this->createForm(MarketWatchType::class, $marketWatch);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $marketWatch->setDofusCharacter($character);
            $em->persist($marketWatch);
            $em->flush();

            $this->addFlash('success', 'Surveillance ajoutée avec succès !');
            return $this->redirectToRoute('app_market_watch_index');
        }

        return $this->render('market_watch/new.html.twig', [
            'form' => $form,
            'character' => $character,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_market_watch_edit')]
    public function edit(
        MarketWatch $marketWatch, 
        Request $request, 
        EntityManagerInterface $em
    ): Response {
        // Vérifier que la surveillance appartient à l'utilisateur
        if ($marketWatch->getDofusCharacter()->getTradingProfile()->getUser() !== $this->getUser()) {
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
        EntityManagerInterface $em
    ): Response {
        // Vérifier que la surveillance appartient à l'utilisateur
        if ($marketWatch->getDofusCharacter()->getTradingProfile()->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($marketWatch);
        $em->flush();

        $this->addFlash('success', 'Surveillance supprimée avec succès !');
        return $this->redirectToRoute('app_market_watch_index');
    }
}