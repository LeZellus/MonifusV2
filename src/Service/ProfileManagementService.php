<?php

namespace App\Service;

use App\Entity\TradingProfile;
use App\Entity\DofusCharacter;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service gérant la logique métier des profils et personnages
 */
class ProfileManagementService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ProfileCharacterService $profileCharacterService,
        private RequestStack $requestStack
    ) {
    }

    public function createProfile(TradingProfile $profile, User $user): void
    {
        $profile->setUser($user);
        $this->em->persist($profile);
        $this->em->flush();
    }

    public function createCharacter(DofusCharacter $character, TradingProfile $profile, User $user): void
    {
        $character->setTradingProfile($profile);
        $this->em->persist($character);
        $this->em->flush();

        // Invalidation des caches et sélection du nouveau personnage
        $this->invalidateCachesAndSelect($character, $user);
    }

    public function selectCharacter(DofusCharacter $character, User $user): void
    {
        $this->invalidateCachesAndSelect($character, $user);
    }

    public function deleteProfile(TradingProfile $profile, User $user): void
    {
        // Vérifier qu'il y a d'autres profils disponibles
        $remainingProfiles = $this->em->getRepository(TradingProfile::class)
            ->createQueryBuilder('tp')
            ->select('COUNT(tp.id)')
            ->where('tp.user = :user')
            ->andWhere('tp.id != :currentProfile')
            ->setParameter('user', $user)
            ->setParameter('currentProfile', $profile->getId())
            ->getQuery()
            ->getSingleScalarResult();

        if ($remainingProfiles === 0) {
            throw new \InvalidArgumentException('Impossible de supprimer le dernier profil.');
        }

        $this->em->remove($profile);
        $this->em->flush();

        // Sélectionner automatiquement un autre personnage
        $this->selectFirstAvailableCharacter($user);
    }

    private function invalidateCachesAndSelect(DofusCharacter $character, User $user): void
    {
        // Invalider les caches
        $this->profileCharacterService->invalidateUserCache($user);

        // Forcer l'invalidation du cache de l'extension Twig
        $request = $this->requestStack->getCurrentRequest();
        if ($request && $request->hasSession()) {
            $session = $request->getSession();
            $session->set('profile_selector_last_update', time());
        }

        // Sélectionner le personnage
        $this->profileCharacterService->setSelectedCharacter($character);
    }

    private function selectFirstAvailableCharacter(User $user): void
    {
        $firstCharacter = $this->em->getRepository(DofusCharacter::class)
            ->createQueryBuilder('c')
            ->join('c.tradingProfile', 'tp')
            ->where('tp.user = :user')
            ->setParameter('user', $user)
            ->orderBy('c.name', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($firstCharacter) {
            $this->invalidateCachesAndSelect($firstCharacter, $user);
        }
    }
}