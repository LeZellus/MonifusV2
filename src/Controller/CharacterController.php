<?php

namespace App\Controller;

use App\Service\CharacterSelectionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Repository\DofusCharacterRepository;
use App\Entity\DofusCharacter;
use App\Form\DofusCharacterType;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/character')]
#[IsGranted('ROLE_USER')]
class CharacterController extends AbstractController
{
    #[Route('/select/{id}', name: 'app_character_select', methods: ['POST'])]
    public function select(
        int $id,
        CharacterSelectionService $characterService,
        DofusCharacterRepository $characterRepository,
        Request $request
    ): Response {
        $character = $characterRepository->createQueryBuilder('c')
            ->join('c.tradingProfile', 'tp')
            ->where('c.id = :id')
            ->andWhere('tp.user = :user')
            ->setParameter('id', $id)
            ->setParameter('user', $this->getUser())
            ->getQuery()
            ->getOneOrNullResult();

        if (!$character) {
            $this->addFlash('error', 'Personnage non trouvé');
            
            // Utiliser referer au lieu de redirection fixe
            $referer = $request->headers->get('referer');
            if ($referer) {
                return $this->redirect($referer);
            }
            return $this->redirectToRoute('app_profile_index');
        }

        // Forcer l'invalidation du cache de l'extension Twig
        if ($request->hasSession()) {
            $session = $request->getSession();
            $session->set('profile_selector_last_update', time());
        }

        $characterService->setSelectedCharacter($character);
        $this->addFlash('success', "Personnage {$character->getName()} sélectionné");

        // Revenir sur la page précédente au lieu de app_lot_index
        $referer = $request->headers->get('referer');
        if ($referer) {
            return $this->redirect($referer);
        }
        
        // Fallback
        return $this->redirectToRoute('app_profile_index');
    }

    #[Route('/{id}/edit', name: 'app_profile_character_edit')]
    public function editCharacter(
        DofusCharacter $character, 
        Request $request, 
        EntityManagerInterface $em
    ): Response {
        if ($character->getTradingProfile()->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(DofusCharacterType::class, $character);
        
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Personnage modifié avec succès !');
            return $this->redirectToRoute('app_profile_index');
        }

        return $this->render('profile/character_edit.html.twig', [
            'form' => $form,
            'character' => $character,
        ]);
    }

    // Uniquement pour la documentation ou avoir le lien dispo quelques part
    #[Route('/character/add', name: 'app_character_add')]
    public function addCharacter(
        TradingProfileRepository $repository,
        CharacterSelectionService $characterService
    ): Response {
        $profiles = $repository->findBy(['user' => $this->getUser()]);
        
        // Aucun profil ? Créer un profil d'abord
        if (empty($profiles)) {
            $this->addFlash('info', 'Créez d\'abord un profil pour organiser vos personnages.');
            return $this->redirectToRoute('app_profile_new');
        }
        
        // Un seul profil ? Créer le personnage directement
        if (count($profiles) === 1) {
            return $this->redirectToRoute('app_profile_character_new', [
                'id' => $profiles[0]->getId()
            ]);
        }
        
        // Plusieurs profils ? Utiliser le profil courant
        $selectedCharacter = $characterService->getSelectedCharacter($this->getUser());
        if ($selectedCharacter) {
            return $this->redirectToRoute('app_profile_character_new', [
                'id' => $selectedCharacter->getTradingProfile()->getId()
            ]);
        }
        
        // Fallback : profil par défaut (le premier)
        return $this->redirectToRoute('app_profile_character_new', [
            'id' => $profiles[0]->getId()
        ]);
    }
}