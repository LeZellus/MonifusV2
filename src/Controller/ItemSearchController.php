<?php

namespace App\Controller;

use App\Repository\ItemRepository;
use App\Enum\ItemType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/items')]
class ItemSearchController extends AbstractController
{
    #[Route('/search', name: 'api_items_search', methods: ['GET'])]
    public function search(Request $request, ItemRepository $itemRepository): JsonResponse
    {
        $query = trim($request->query->get('q', ''));
        $limit = min((int) $request->query->get('limit', 20), 50);
        
        if (strlen($query) < 2) {
            return $this->json(['items' => []]);
        }
        
        try {
            $items = $itemRepository->searchByName($query, $limit);
            
            $itemsData = array_map(function($item) {
                $typeLabel = $this->getTypeLabel($item['itemType']);
                
                return [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'level' => $item['level'] ?? null,
                    'type' => $typeLabel,
                    'img_url' => $item['imgUrl'] ?? null, // Ajout du champ image
                    'display' => $item['name'] . ($item['level'] ? ' (Niv.' . $item['level'] . ')' : '')
                ];
            }, $items);
            
            return $this->json(['items' => $itemsData]);
        } catch (\Exception $e) {
            return $this->json(['items' => []], 500);
        }
    }

    #[Route('/search/resources', name: 'api_items_search_resources', methods: ['GET'])]
    public function searchResources(Request $request, ItemRepository $itemRepository): JsonResponse
    {
        $query = trim($request->query->get('q', ''));
        $limit = min((int) $request->query->get('limit', 20), 50);
        
        if (strlen($query) < 2) {
            return $this->json(['items' => []]);
        }
        
        try {
            $items = $itemRepository->searchByName($query, $limit);
            
            $itemsData = array_map(function($item) {
                return [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'level' => $item['level'] ?? null,
                    'type' => 'Ressource',
                    'img_url' => $item['imgUrl'] ?? null, // Ajout du champ image
                    'display' => $item['name'] . ($item['level'] ? ' (Niv.' . $item['level'] . ')' : '')
                ];
            }, $items);
            
            return $this->json(['items' => $itemsData]);
        } catch (\Exception $e) {
            return $this->json(['items' => []], 500);
        }
    }
    
    private function getTypeLabel($itemType): string
    {
        if (!$itemType) {
            return 'Divers';
        }
        
        // Si c'est une string (valeur de l'enum en base)
        if (is_string($itemType)) {
            return match($itemType) {
                'Resource' => 'Ressource',
                'Equipment' => 'Équipement', 
                'Consumable' => 'Consommable',
                default => 'Divers'
            };
        }
        
        // Si c'est l'enum ItemType directement
        if ($itemType instanceof ItemType) {
            return match($itemType) {
                ItemType::RESOURCE => 'Ressource',
                ItemType::EQUIPMENT => 'Équipement',
                ItemType::CONSUMABLE => 'Consommable',
                default => 'Divers'
            };
        }
        
        return 'Divers';
    }
}