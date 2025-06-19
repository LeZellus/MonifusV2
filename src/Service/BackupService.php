<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\TradingProfileRepository;
use App\Repository\LotGroupRepository;
use App\Repository\LotUnitRepository;
use App\Repository\MarketWatchRepository;
use Symfony\Component\HttpFoundation\Response;

class BackupService
{
    public function __construct(
        private TradingProfileRepository $tradingProfileRepository,
        private LotGroupRepository $lotGroupRepository,
        private LotUnitRepository $lotUnitRepository,
        private MarketWatchRepository $marketWatchRepository
    ) {
    }

    public function exportUserData(User $user): Response
    {
        // Récupérer toutes les données utilisateur
        $data = [
            'export_date' => date('Y-m-d H:i:s'),
            'user_email' => $user->getEmail(),
            'profiles' => [],
            'characters' => [],
            'lots' => [],
            'sales' => [],
            'market_watch' => []
        ];

        // Profils de trading
        $profiles = $this->tradingProfileRepository->findBy(['user' => $user]);
        foreach ($profiles as $profile) {
            $data['profiles'][] = [
                'name' => $profile->getName(),
                'description' => $profile->getDescription(),
                'created_at' => $profile->getCreatedAt()->format('Y-m-d H:i:s')
            ];

            // Personnages du profil
            foreach ($profile->getDofusCharacters() as $character) {
                $data['characters'][] = [
                    'profile_name' => $profile->getName(),
                    'name' => $character->getName(),
                    'server' => $character->getServer()->getName(),
                    'classe' => $character->getClasse()->getName()
                ];

                // Lots du personnage
                foreach ($character->getLotGroups() as $lot) {
                    $lotData = [
                        'character_name' => $character->getName(),
                        'item_name' => $lot->getItem()->getName(),
                        'lot_size' => $lot->getLotSize(),
                        'sale_unit' => $lot->getSaleUnit()->value,
                        'buy_price' => $lot->getBuyPricePerLot(),
                        'sell_price' => $lot->getSellPricePerLot(),
                        'status' => $lot->getStatus()->value,
                        'created_at' => $lot->getCreatedAt()->format('Y-m-d H:i:s')
                    ];
                    $data['lots'][] = $lotData;

                    // Ventes du lot
                    foreach ($lot->getLotUnits() as $sale) {
                        $data['sales'][] = [
                            'character_name' => $character->getName(),
                            'item_name' => $lot->getItem()->getName(),
                            'sold_at' => $sale->getSoldAt()->format('Y-m-d H:i:s'),
                            'actual_price' => $sale->getActualSellPrice(),
                            'notes' => $sale->getNotes()
                        ];
                    }
                }

                // Surveillances du personnage
                foreach ($character->getMarketWatches() as $watch) {
                    $data['market_watch'][] = [
                        'character_name' => $character->getName(),
                        'item_name' => $watch->getItem()->getName(),
                        'lot_size' => $watch->getLotSize(),
                        'observed_price' => $watch->getObservedPrice(),
                        'price_type' => $watch->getPriceType()->value,
                        'notes' => $watch->getNotes(),
                        'updated_at' => $watch->getUpdatedAt()->format('Y-m-d H:i:s')
                    ];
                }
            }
        }

        // Créer le fichier JSON
        $filename = 'dofus_trading_backup_' . $user->getId() . '_' . date('Y-m-d_H-i-s') . '.json';
        
        $response = new Response(json_encode($data, JSON_PRETTY_PRINT));
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    public function generateDataSummary(User $user): array
    {
        $profiles = $this->tradingProfileRepository->findBy(['user' => $user]);
        
        $summary = [
            'profiles_count' => count($profiles),
            'characters_count' => 0,
            'lots_count' => 0,
            'sales_count' => 0,
            'watches_count' => 0
        ];

        foreach ($profiles as $profile) {
            $summary['characters_count'] += $profile->getDofusCharacters()->count();
            
            foreach ($profile->getDofusCharacters() as $character) {
                $summary['lots_count'] += $character->getLotGroups()->count();
                $summary['watches_count'] += $character->getMarketWatches()->count();
                
                foreach ($character->getLotGroups() as $lot) {
                    $summary['sales_count'] += $lot->getLotUnits()->count();
                }
            }
        }

        return $summary;
    }
}