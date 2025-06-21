<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\TradingProfile;
use App\Entity\DofusCharacter;
use App\Entity\LotGroup;
use App\Entity\LotUnit;
use App\Entity\MarketWatch;
use App\Repository\TradingProfileRepository;
use App\Repository\LotGroupRepository;
use App\Repository\LotUnitRepository;
use App\Repository\MarketWatchRepository;
use App\Repository\ItemRepository;
use App\Repository\ServerRepository;
use App\Repository\ClasseRepository;
use App\Enum\LotStatus;
use App\Enum\SaleUnit;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

class BackupService
{
    public function __construct(
        private TradingProfileRepository $tradingProfileRepository,
        private LotGroupRepository $lotGroupRepository,
        private LotUnitRepository $lotUnitRepository,
        private MarketWatchRepository $marketWatchRepository,
        private EntityManagerInterface $entityManager,
        private ItemRepository $itemRepository,
        private ServerRepository $serverRepository,
        private ClasseRepository $classeRepository
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
                        'price_per_unit' => $watch->getPricePerUnit(),
                        'price_per_10' => $watch->getPricePer10(),
                        'price_per_100' => $watch->getPricePer100(),
                        'notes' => $watch->getNotes(),
                        'observed_at' => $watch->getObservedAt()->format('Y-m-d H:i:s'),
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

    /**
     * Importe les données utilisateur depuis un fichier JSON
     */
    public function importUserData(User $user, array $data): array
    {
        $stats = [
            'profiles_created' => 0,
            'characters_created' => 0,
            'lots_created' => 0,
            'sales_created' => 0,
            'watches_created' => 0,
            'errors' => []
        ];

        try {
            // Validation basique du format
            if (!isset($data['profiles']) || !is_array($data['profiles'])) {
                throw new \InvalidArgumentException('Format de fichier invalide');
            }

            // Créer les profils
            $profilesMap = [];
            foreach ($data['profiles'] as $profileData) {
                try {
                    $profile = new TradingProfile();
                    $profile->setName($profileData['name']);
                    $profile->setDescription($profileData['description'] ?? '');
                    $profile->setUser($user);
                    
                    $this->entityManager->persist($profile);
                    $this->entityManager->flush(); // Flush pour obtenir l'ID
                    
                    $profilesMap[$profileData['name']] = $profile;
                    $stats['profiles_created']++;
                } catch (\Exception $e) {
                    $stats['errors'][] = "Erreur profil '{$profileData['name']}': " . $e->getMessage();
                }
            }

            // Créer les personnages
            $charactersMap = [];
            if (isset($data['characters'])) {
                foreach ($data['characters'] as $characterData) {
                    try {
                        if (!isset($profilesMap[$characterData['profile_name']])) {
                            throw new \Exception("Profil '{$characterData['profile_name']}' non trouvé");
                        }

                        $server = $this->serverRepository->findOneBy(['name' => $characterData['server']]);
                        $classe = $this->classeRepository->findOneBy(['name' => $characterData['classe']]);
                        
                        if (!$server || !$classe) {
                            throw new \Exception("Serveur ou classe non trouvé");
                        }

                        $character = new DofusCharacter();
                        $character->setName($characterData['name']);
                        $character->setServer($server);
                        $character->setClasse($classe);
                        $character->setTradingProfile($profilesMap[$characterData['profile_name']]);
                        
                        $this->entityManager->persist($character);
                        $this->entityManager->flush();
                        
                        $charactersMap[$characterData['name']] = $character;
                        $stats['characters_created']++;
                    } catch (\Exception $e) {
                        $stats['errors'][] = "Erreur personnage '{$characterData['name']}': " . $e->getMessage();
                    }
                }
            }

            // Créer les lots
            $lotsMap = [];
            if (isset($data['lots'])) {
                foreach ($data['lots'] as $lotData) {
                    try {
                        if (!isset($charactersMap[$lotData['character_name']])) {
                            throw new \Exception("Personnage '{$lotData['character_name']}' non trouvé");
                        }

                        $item = $this->itemRepository->findOneBy(['name' => $lotData['item_name']]);
                        if (!$item) {
                            throw new \Exception("Item '{$lotData['item_name']}' non trouvé");
                        }

                        $lot = new LotGroup();
                        $lot->setItem($item);
                        $lot->setDofusCharacter($charactersMap[$lotData['character_name']]);
                        $lot->setLotSize($lotData['lot_size']);
                        $lot->setSaleUnit(SaleUnit::from($lotData['sale_unit']));
                        $lot->setBuyPricePerLot($lotData['buy_price']);
                        $lot->setSellPricePerLot($lotData['sell_price']);
                        $lot->setStatus(LotStatus::from($lotData['status']));
                        
                        $this->entityManager->persist($lot);
                        $this->entityManager->flush();
                        
                        $lotsMap[$lotData['character_name'] . '_' . $lotData['item_name'] . '_' . $lotData['created_at']] = $lot;
                        $stats['lots_created']++;
                    } catch (\Exception $e) {
                        $stats['errors'][] = "Erreur lot '{$lotData['item_name']}': " . $e->getMessage();
                    }
                }
            }

            // Créer les ventes
            if (isset($data['sales'])) {
                foreach ($data['sales'] as $saleData) {
                    try {
                        // Trouver le lot correspondant (approximatif)
                        $lotKey = $saleData['character_name'] . '_' . $saleData['item_name'];
                        $lot = null;
                        foreach ($lotsMap as $key => $lotObj) {
                            if (strpos($key, $lotKey) === 0) {
                                $lot = $lotObj;
                                break;
                            }
                        }
                        
                        if (!$lot) {
                            throw new \Exception("Lot correspondant non trouvé");
                        }

                        $sale = new LotUnit();
                        $sale->setLotGroup($lot);
                        $sale->setQuantitySold(1); // Valeur par défaut
                        $sale->setActualSellPrice($saleData['actual_price']);
                        $sale->setSoldAt(new \DateTime($saleData['sold_at']));
                        $sale->setNotes($saleData['notes'] ?? '');
                        
                        $this->entityManager->persist($sale);
                        $stats['sales_created']++;
                    } catch (\Exception $e) {
                        $stats['errors'][] = "Erreur vente: " . $e->getMessage();
                    }
                }
            }

            // Créer les surveillances
            if (isset($data['market_watch'])) {
                foreach ($data['market_watch'] as $watchData) {
                    try {
                        if (!isset($charactersMap[$watchData['character_name']])) {
                            throw new \Exception("Personnage '{$watchData['character_name']}' non trouvé");
                        }

                        $item = $this->itemRepository->findOneBy(['name' => $watchData['item_name']]);
                        if (!$item) {
                            throw new \Exception("Item '{$watchData['item_name']}' non trouvé");
                        }

                        $watch = new MarketWatch();
                        $watch->setItem($item);
                        $watch->setDofusCharacter($charactersMap[$watchData['character_name']]);
                        $watch->setPricePerUnit($watchData['price_per_unit']);
                        $watch->setPricePer10($watchData['price_per_10']);
                        $watch->setPricePer100($watchData['price_per_100']);
                        $watch->setNotes($watchData['notes'] ?? '');
                        $watch->setObservedAt(new \DateTimeImmutable($watchData['observed_at']));
                        
                        $this->entityManager->persist($watch);
                        $stats['watches_created']++;
                    } catch (\Exception $e) {
                        $stats['errors'][] = "Erreur surveillance '{$watchData['item_name']}': " . $e->getMessage();
                    }
                }
            }

            $this->entityManager->flush();
            
        } catch (\Exception $e) {
            $stats['errors'][] = "Erreur générale: " . $e->getMessage();
        }

        return $stats;
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