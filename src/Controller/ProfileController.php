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
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile')]
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    #[Route('/', name: 'app_profile_index')]
    public function index(
        TradingProfileRepository $repository,
        CharacterSelectionService $characterService
    ): Response {
        $profiles = $repository->findBy(['user' => $this->getUser()]);
        $selectedCharacter = $characterService->getSelectedCharacter($this->getUser());
        $allCharacters = $characterService->getUserCharacters($this->getUser());
        
        return $this->render('profile/index.html.twig', [
            'profiles' => $profiles,
            'selectedCharacter' => $selectedCharacter,
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
        CharacterSelectionService $characterService
    ): Response {
        // Vérifier que le personnage appartient à l'utilisateur
        if ($character->getTradingProfile()->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $characterService->setSelectedCharacter($character);
        $this->addFlash('success', "Personnage {$character->getName()} sélectionné !");
        
        return $this->redirectToRoute('app_profile_index');
    }
}