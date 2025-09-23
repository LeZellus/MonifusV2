<?php

namespace App\Controller;

use App\Service\CharacterSelectionService;
use App\Service\ProfileSelectorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/debug-compare')]
#[IsGranted('ROLE_USER')]
class CompareProfileDebugController extends AbstractController
{
    #[Route('/profile-page', name: 'app_debug_compare_profile')]
    public function compareProfile(
        CharacterSelectionService $characterService,
        ProfileSelectorService $profileService,
        RequestStack $requestStack
    ): JsonResponse {
        $user = $this->getUser();
        $session = $requestStack->getSession();

        // Simuler exactement ce que fait la page /profile
        $selectorData = $profileService->getSelectorData($user);
        $selectedCharacter = $characterService->getSelectedCharacter($user);

        return new JsonResponse([
            'page' => 'PROFILE_PAGE_SIMULATION',
            'session' => [
                'selected_profile_id' => $session->get('selected_profile_id'),
                'selected_character_id' => $session->get('selected_character_id'),
                'profile_selector_last_update' => $session->get('profile_selector_last_update'),
            ],
            'profile_service_data' => [
                'current_profile' => $selectorData['currentProfile'] ? [
                    'id' => $selectorData['currentProfile']->getId(),
                    'name' => $selectorData['currentProfile']->getName(),
                    'character_count' => $selectorData['currentProfile']->getDofusCharacters()->count(),
                ] : null,
                'selected_character' => $selectorData['selectedCharacter'] ? [
                    'id' => $selectorData['selectedCharacter']->getId(),
                    'name' => $selectorData['selectedCharacter']->getName(),
                ] : null,
            ],
            'character_service_data' => [
                'selected_character' => $selectedCharacter ? [
                    'id' => $selectedCharacter->getId(),
                    'name' => $selectedCharacter->getName(),
                ] : null,
            ],
            'timestamp' => time(),
        ]);
    }

    #[Route('/other-page', name: 'app_debug_compare_other')]
    public function compareOther(
        CharacterSelectionService $characterService,
        ProfileSelectorService $profileService,
        RequestStack $requestStack
    ): JsonResponse {
        $user = $this->getUser();
        $session = $requestStack->getSession();

        // Simuler exactement ce que fait une autre page (analytics, lot, etc.)
        $selectorData = $profileService->getSelectorData($user);
        $selectedCharacter = $characterService->getSelectedCharacter($user);

        return new JsonResponse([
            'page' => 'OTHER_PAGE_SIMULATION',
            'session' => [
                'selected_profile_id' => $session->get('selected_profile_id'),
                'selected_character_id' => $session->get('selected_character_id'),
                'profile_selector_last_update' => $session->get('profile_selector_last_update'),
            ],
            'profile_service_data' => [
                'current_profile' => $selectorData['currentProfile'] ? [
                    'id' => $selectorData['currentProfile']->getId(),
                    'name' => $selectorData['currentProfile']->getName(),
                    'character_count' => $selectorData['currentProfile']->getDofusCharacters()->count(),
                ] : null,
                'selected_character' => $selectorData['selectedCharacter'] ? [
                    'id' => $selectorData['selectedCharacter']->getId(),
                    'name' => $selectorData['selectedCharacter']->getName(),
                ] : null,
            ],
            'character_service_data' => [
                'selected_character' => $selectedCharacter ? [
                    'id' => $selectedCharacter->getId(),
                    'name' => $selectedCharacter->getName(),
                ] : null,
            ],
            'timestamp' => time(),
        ]);
    }

    #[Route('/twig-extension-direct', name: 'app_debug_twig_extension')]
    public function testTwigExtension(
        RequestStack $requestStack
    ): JsonResponse {
        $user = $this->getUser();

        // Simuler exactement ce que fait l'extension Twig
        $request = $requestStack->getCurrentRequest();
        $lastUpdate = 0;
        $currentTime = time();

        if ($request && $request->hasSession()) {
            $session = $request->getSession();
            $lastUpdate = $session->get('profile_selector_last_update', 0);
        }

        $bypassCache = ($currentTime - $lastUpdate) < 120;

        return new JsonResponse([
            'extension_simulation' => true,
            'current_time' => $currentTime,
            'last_update' => $lastUpdate,
            'time_diff' => $currentTime - $lastUpdate,
            'bypass_cache' => $bypassCache,
            'cache_key_would_be' => 'profile_selector_data_user_' . $user->getId() . '_' . $lastUpdate,
        ]);
    }
}