<?php

namespace App\Controller;

use App\Service\CharacterSelectionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Repository\DofusCharacterRepository;

#[Route('/character')]
#[IsGranted('ROLE_USER')]
class CharacterController extends AbstractController
{
    #[Route('/select/{id}', name: 'app_character_select')]
    public function select(
        int $id,
        CharacterSelectionService $characterService,
        DofusCharacterRepository $characterRepository,
        Request $request
    ): JsonResponse {
        // Vérifier que le personnage appartient à l'utilisateur
        $character = $characterRepository->createQueryBuilder('c')
            ->join('c.tradingProfile', 'tp')
            ->where('c.id = :id')
            ->andWhere('tp.user = :user')
            ->setParameter('id', $id)
            ->setParameter('user', $this->getUser())
            ->getQuery()
            ->getOneOrNullResult();

        if (!$character) {
            return new JsonResponse(['error' => 'Personnage non trouvé'], 404);
        }

        $characterService->setSelectedCharacter($character);

        return new JsonResponse([
            'success' => true,
            'character' => [
                'id' => $character->getId(),
                'name' => $character->getName(),
                'server' => $character->getServer()->getName(),
                'classe' => $character->getClasse()->getName()
            ]
        ]);
    }

    #[Route('/current', name: 'app_character_current')]
    public function current(CharacterSelectionService $characterService): JsonResponse
    {
        $character = $characterService->getSelectedCharacter($this->getUser());

        if (!$character) {
            return new JsonResponse(['character' => null]);
        }

        return new JsonResponse([
            'character' => [
                'id' => $character->getId(),
                'name' => $character->getName(),
                'server' => $character->getServer()->getName(),
                'classe' => $character->getClasse()->getName()
            ]
        ]);
    }
}