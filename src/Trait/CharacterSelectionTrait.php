<?php

namespace App\Trait;

use App\Entity\DofusCharacter;
use App\Service\ProfileCharacterService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Trait pour gérer la sélection de personnages dans les contrôleurs
 * Évite la répétition de code identique dans 7+ contrôleurs
 */
trait CharacterSelectionTrait
{
    /**
     * Récupère le personnage sélectionné et tous les personnages de l'utilisateur
     */
    protected function getCharacterData(ProfileCharacterService $profileCharacterService): array
    {
        $selectedCharacter = $profileCharacterService->getSelectedCharacter($this->getUser());
        $characters = $profileCharacterService->getUserCharacters($this->getUser());

        return [$selectedCharacter, $characters];
    }

    /**
     * Récupère le personnage sélectionné avec validation automatique
     * Redirige vers le profil si aucun personnage n'est sélectionné
     */
    protected function getSelectedCharacterOrRedirect(
        ProfileCharacterService $profileCharacterService,
        string $message = 'Créez d\'abord un personnage.'
    ): DofusCharacter|Response {
        $selectedCharacter = $profileCharacterService->getSelectedCharacter($this->getUser());

        if (!$selectedCharacter) {
            $this->addFlash('warning', $message);
            return $this->redirectToRoute('app_profile_index');
        }

        return $selectedCharacter;
    }

    /**
     * Vérifie que l'utilisateur peut accéder à une ressource liée à un personnage
     */
    protected function canAccessCharacterResource($resource, DofusCharacter $userCharacter): bool
    {
        if (method_exists($resource, 'getDofusCharacter')) {
            return $resource->getDofusCharacter()->getId() === $userCharacter->getId();
        }

        return false;
    }

    /**
     * Crée une réponse d'erreur JSON pour les requêtes AJAX
     */
    protected function createCharacterErrorResponse(string $message = 'Aucun personnage sélectionné'): Response
    {
        return new \Symfony\Component\HttpFoundation\JsonResponse(['error' => $message], 400);
    }
}