<?php
// src/Twig/ProfileSelectorExtension.php

namespace App\Twig;

use App\Service\ProfileSelectorService;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class ProfileSelectorExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private ProfileSelectorService $profileSelectorService,
        private Security $security
    ) {}

    public function getGlobals(): array
    {
        $user = $this->security->getUser();
        
        if (!$user) {
            return ['selectorData' => null];
        }

        return [
            'selectorData' => $this->profileSelectorService->getSelectorData($user)
        ];
    }
}