<?php

namespace App\Service;

use App\Entity\LotGroup;
use App\Entity\DofusCharacter;
use App\Repository\LotGroupRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service gérant la logique métier des lots
 */
class LotManagementService
{
    public function __construct(
        private EntityManagerInterface $em,
        private LotGroupRepository $lotRepository,
        private CacheInvalidationService $cacheInvalidation,
        private ProfileCharacterService $profileCharacterService
    ) {
    }

    public function getAvailableLotsForCharacter(?DofusCharacter $character): array
    {
        return $character ? $this->lotRepository->findAvailableByCharacter($character) : [];
    }

    public function searchLotsByItemName(DofusCharacter $character, string $searchQuery): array
    {
        return $this->lotRepository->searchByItemName($character, $searchQuery);
    }

    public function createLot(LotGroup $lotGroup, DofusCharacter $character): void
    {
        // S'assurer que le personnage est managé par Doctrine
        $managedCharacter = $this->em->getRepository(DofusCharacter::class)->find($character->getId());
        if (!$managedCharacter) {
            throw new \InvalidArgumentException('Personnage introuvable.');
        }

        $lotGroup->setDofusCharacter($managedCharacter);
        $this->em->persist($lotGroup);
        $this->em->flush();

        $this->invalidateCaches($managedCharacter->getTradingProfile()->getUser());
    }

    public function updateLot(LotGroup $lotGroup): void
    {
        $this->em->flush();
        $this->invalidateCaches($lotGroup->getDofusCharacter()->getTradingProfile()->getUser());
    }

    public function deleteLot(LotGroup $lotGroup): void
    {
        $user = $lotGroup->getDofusCharacter()->getTradingProfile()->getUser();

        $this->em->remove($lotGroup);
        $this->em->flush();

        $this->invalidateCaches($user);
    }

    public function canUserAccessLot(LotGroup $lotGroup, DofusCharacter $userCharacter): bool
    {
        return $lotGroup->getDofusCharacter()->getId() === $userCharacter->getId();
    }

    private function invalidateCaches($user): void
    {
        $this->profileCharacterService->forceInvalidateCountsCache($user);
        $this->cacheInvalidation->invalidateUserStatsAndMarkActivity($user);
    }
}