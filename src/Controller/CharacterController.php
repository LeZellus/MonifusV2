<?php

namespace App\Controller;

use App\Service\CharacterSelectionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Repository\DofusCharacterRepository;

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

}