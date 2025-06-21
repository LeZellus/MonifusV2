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
use Symfony\Component\HttpFoundation\RequestStack; // AJOUT
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile')]
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    // AJOUT du constructeur pour RequestStack
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
        $selectedCharacter = $characterService->getSelectedCharacter($this->getUser());
        $allCharacters = $characterService->getUserCharacters($this->getUser());
        
        // NOUVEAU : Récupérer le profil sélectionné depuis la session
        $session = $this->requestStack->getSession();
        $selectedProfileId = $session->get('selected_profile_id');
        $selectedProfile = null;
        
        // Trouver le profil sélectionné
        if ($selectedProfileId) {
            foreach ($profiles as $profile) {
                if ($profile->getId() === $selectedProfileId) {
                    $selectedProfile = $profile;
                    break;
                }
            }
        }
        
        // Si pas de profil en session ou profil introuvable, utiliser celui du personnage ou le premier
        if (!$selectedProfile) {
            if ($selectedCharacter) {
                $selectedProfile = $selectedCharacter->getTradingProfile();
                // Sauvegarder en session pour la prochaine fois
                $session->set('selected_profile_id', $selectedProfile->getId());
            } else {
                $selectedProfile = $profiles[0] ?? null;
                if ($selectedProfile) {
                    $session->set('selected_profile_id', $selectedProfile->getId());
                }
            }
        }
        
        // Code des stats (inchangé)
        foreach ($profiles as $profile) {
            foreach ($profile->getDofusCharacters() as $character) {
                $lotsCount = $character->getLotGroups()->count();
                $watchesCount = $character->getMarketWatches()->count();
                $soldLotsCount = 0;
                $availableLotsCount = 0;
                
                foreach ($character->getLotGroups() as $lot) {
                    if ($lot->getStatus()->value === 'sold') {
                        $soldLotsCount++;
                    } else {
                        $availableLotsCount++;
                    }
                }
                
                $character->tempLotsCount = $lotsCount;
                $character->tempWatchesCount = $watchesCount;
                $character->tempSoldLotsCount = $soldLotsCount;
                $character->tempAvailableLotsCount = $availableLotsCount;
            }
        }
        
        return $this->render('profile/index.html.twig', [
            'profiles' => $profiles,
            'selectedCharacter' => $selectedCharacter,
            'selectedProfile' => $selectedProfile, // NOUVEAU
            'characters' => $allCharacters,
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
        // Vérifier que le profil appartient à l'utilisateur
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
            
            // Auto-sélectionner le personnage nouvellement créé
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
        Request $request  // AJOUTER ce paramètre
    ): Response {
        // Vérifier que le personnage appartient à l'utilisateur
        if ($character->getTradingProfile()->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $characterService->setSelectedCharacter($character);
        $this->addFlash('success', "Personnage {$character->getName()} sélectionné !");

        // Revenir sur la page précédente au lieu de app_profile_index
        $referer = $request->headers->get('referer');
        if ($referer) {
            return $this->redirect($referer);
        }
        
        // Fallback
        return $this->redirectToRoute('app_profile_index');
    }

    #[Route('/switch/{id}', name: 'app_profile_switch')]
    public function switchProfile(
        TradingProfile $profile,
        CharacterSelectionService $characterService,
        Request $request
    ): Response {
        // Vérifier que le profil appartient à l'utilisateur
        if ($profile->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $session = $this->requestStack->getSession();
        
        // TOUJOURS nettoyer la sélection de personnage d'abord
        $session->remove('selected_character_id');
        
        // Puis sauvegarder le nouveau profil
        $session->set('selected_profile_id', $profile->getId());

        // Sélectionner le premier personnage de ce profil s'il existe
        $characters = $profile->getDofusCharacters();
        if ($characters->count() > 0) {
            $firstCharacter = $characters->first();
            $characterService->setSelectedCharacter($firstCharacter);
            $this->addFlash('success', "Profil '{$profile->getName()}' activé avec le personnage {$firstCharacter->getName()}");
        } else {
            $this->addFlash('info', "Profil '{$profile->getName()}' activé. Ajoutez un personnage pour commencer !");
        }

        // Utiliser le referer pour revenir sur la page précédente
        $referer = $request->headers->get('referer');
        if ($referer) {
            return $this->redirect($referer);
        }

        // Fallback
        return $this->redirectToRoute('app_profile_index');
    }
    
    #[Route('/{id}/edit', name: 'app_profile_edit')]
    public function edit(
        TradingProfile $profile,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        // Vérifier que le profil appartient à l'utilisateur
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

    #[Route('/{id}/delete', name: 'app_profile_delete', methods: ['POST'])]
    public function deleteProfile(
        TradingProfile $profile,
        EntityManagerInterface $em
    ): Response {
        // Vérifier que le profil appartient à l'utilisateur
        if ($profile->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $profileName = $profile->getName();
        
        // Supprimer tous les personnages et leurs données associées
        foreach ($profile->getDofusCharacters() as $character) {
            $em->remove($character);
        }
        
        $em->remove($profile);
        $em->flush();

        $this->addFlash('success', "Profil '{$profileName}' supprimé avec succès.");
        return $this->redirectToRoute('app_profile_index');
    }

    #[Route('/character/{id}/delete', name: 'app_profile_character_delete', methods: ['POST'])]
    public function deleteCharacter(
        DofusCharacter $character,
        EntityManagerInterface $em,
        CharacterSelectionService $characterService
    ): Response {
        // Vérifier que le personnage appartient à l'utilisateur
        if ($character->getTradingProfile()->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $characterName = $character->getName();
        $selectedCharacter = $characterService->getSelectedCharacter($this->getUser());
        
        // Si on supprime le personnage actif, désélectionner
        if ($selectedCharacter && $selectedCharacter->getId() === $character->getId()) {
            // Sélectionner un autre personnage du même profil ou null
            $otherCharacters = $character->getTradingProfile()->getDofusCharacters();
            $newSelected = null;
            foreach ($otherCharacters as $otherChar) {
                if ($otherChar->getId() !== $character->getId()) {
                    $newSelected = $otherChar;
                    break;
                }
            }
            
            if ($newSelected) {
                $characterService->setSelectedCharacter($newSelected);
            }
        }
        
        $em->remove($character);
        $em->flush();

        $this->addFlash('success', "Personnage '{$characterName}' supprimé avec succès.");
        return $this->redirectToRoute('app_profile_index');
    }
}