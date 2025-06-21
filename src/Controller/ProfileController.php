<?php

namespace App\Controller;

use App\Entity\TradingProfile;
use App\Entity\DofusCharacter;
use App\Form\TradingProfileType;
use App\Form\DofusCharacterType;
use App\Repository\TradingProfileRepository;
use App\Service\CharacterSelectionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile')]
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    public function __construct(
        private RequestStack $requestStack
    ) {
    }

    #[Route('/', name: 'app_profile_index')]
    public function index(
        TradingProfileRepository $repository,
        CharacterSelectionService $characterService
    ): Response {
        $profiles = $repository->findBy(['user' => $this->getUser()]);
        
        // Plus besoin de toute la logique complexe - le ProfileSelectorService via Twig Extension s'en charge
        return $this->render('profile/index.html.twig', [
            'profiles' => $profiles
        ]);
    }

    #[Route('/new', name: 'app_profile_new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $profile = new TradingProfile();
        $form = $this->createForm(TradingProfileType::class, $profile);
        
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $profile->setUser($this->getUser());
            $em->persist($profile);
            $em->flush();
            
            $this->addFlash('success', 'Profil de trading créé avec succès !');
            return $this->redirectToRoute('app_profile_index');
        }

        return $this->render('profile/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/character/new', name: 'app_profile_character_new')]
    public function newCharacter(
        TradingProfile $profile, 
        Request $request, 
        EntityManagerInterface $em,
        CharacterSelectionService $characterService
    ): Response {
        if ($profile->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $character = new DofusCharacter();
        $form = $this->createForm(DofusCharacterType::class, $character);
        
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $character->setTradingProfile($profile);
            $em->persist($character);
            $em->flush();
            
            $characterService->setSelectedCharacter($character);
            
            $this->addFlash('success', 'Personnage ajouté et sélectionné avec succès !');
            return $this->redirectToRoute('app_profile_index');
        }

        return $this->render('profile/character_new.html.twig', [
            'form' => $form,
            'profile' => $profile,
        ]);
    }

    #[Route('/character/{id}/select', name: 'app_profile_character_select')]
    public function selectCharacter(
        DofusCharacter $character,
        CharacterSelectionService $characterService,
        Request $request
    ): Response {
        if ($character->getTradingProfile()->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $characterService->setSelectedCharacter($character);
        $this->addFlash('success', "Personnage {$character->getName()} sélectionné !");

        $referer = $request->headers->get('referer');
        if ($referer) {
            return $this->redirect($referer);
        }
        
        return $this->redirectToRoute('app_profile_index');
    }

    #[Route('/switch/{id}', name: 'app_profile_switch')]
    public function switchProfile(
        TradingProfile $profile,
        CharacterSelectionService $characterService,
        Request $request
    ): Response {
        if ($profile->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $session = $this->requestStack->getSession();
        
        // Nettoyer la sélection de personnage
        $session->remove('selected_character_id');
        
        // Sauvegarder le nouveau profil
        $session->set('selected_profile_id', $profile->getId());

        // Sélectionner le premier personnage de ce profil
        $characters = $profile->getDofusCharacters();
        if ($characters->count() > 0) {
            $firstCharacter = $characters->first();
            $characterService->setSelectedCharacter($firstCharacter);
            $this->addFlash('success', "Profil '{$profile->getName()}' activé avec le personnage {$firstCharacter->getName()}");
        } else {
            $this->addFlash('info', "Profil '{$profile->getName()}' activé. Ajoutez un personnage pour commencer !");
        }

        $referer = $request->headers->get('referer');
        if ($referer) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_profile_index');
    }
    
    #[Route('/{id}/edit', name: 'app_profile_edit')]
    public function edit(
        TradingProfile $profile,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        if ($profile->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(TradingProfileType::class, $profile);
        
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            
            $this->addFlash('success', 'Profil mis à jour avec succès !');
            return $this->redirectToRoute('app_profile_index');
        }

        return $this->render('profile/edit.html.twig', [
            'form' => $form,
            'profile' => $profile,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_profile_delete')]
    public function delete(
        TradingProfile $profile,
        EntityManagerInterface $em,
        Request $request
    ): Response {
        if ($profile->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        // Nettoyer la session si c'était le profil actif
        $session = $this->requestStack->getSession();
        if ($session->get('selected_profile_id') === $profile->getId()) {
            $session->remove('selected_profile_id');
            $session->remove('selected_character_id');
        }

        $em->remove($profile);
        $em->flush();

        $this->addFlash('success', 'Profil supprimé avec succès !');
        return $this->redirectToRoute('app_profile_index');
    }

    #[Route('/character/{id}/delete', name: 'app_profile_character_delete')]
    public function deleteCharacter(
        DofusCharacter $character,
        EntityManagerInterface $em,
        CharacterSelectionService $characterService
    ): Response {
        if ($character->getTradingProfile()->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        // Si c'était le personnage sélectionné, le réinitialiser
        $selectedCharacter = $characterService->getSelectedCharacter($this->getUser());
        if ($selectedCharacter && $selectedCharacter->getId() === $character->getId()) {
            $session = $this->requestStack->getSession();
            $session->remove('selected_character_id');
        }

        $em->remove($character);
        $em->flush();

        $this->addFlash('success', 'Personnage supprimé avec succès !');
        return $this->redirectToRoute('app_profile_index');
    }
}