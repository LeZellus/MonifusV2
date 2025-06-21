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
        
        // Requête optimisée avec toutes les infos
        $items = $itemRepository->createQueryBuilder('i')
            ->select('i.id, i.name, i.level, i.itemType')
            ->where('i.name LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->setMaxResults($limit)
            ->orderBy('i.name', 'ASC')
            ->getQuery()
            ->getArrayResult();
        
        $itemsData = array_map(function($item) {
            $typeLabel = $this->getTypeLabel($item['itemType']);
            
            return [
                'id' => $item['id'],
                'name' => $item['name'],
                'level' => $item['level'] ?? null,
                'type' => $typeLabel,
                'display' => $item['name'] . ($item['level'] ? ' (Niv.' . $item['level'] . ')' : '')
            ];
        }, $items);
        
        return $this->json(['items' => $itemsData]);
    }

    #[Route('/search/resources', name: 'api_items_search_resources', methods: ['GET'])]
    public function searchResources(Request $request, ItemRepository $itemRepository): JsonResponse
    {
        $query = trim($request->query->get('q', ''));
        $limit = min((int) $request->query->get('limit', 20), 50);
        
        if (strlen($query) < 2) {
            return $this->json(['items' => []]);
        }
        
        $items = $itemRepository->createQueryBuilder('i')
            ->select('i.id, i.name, i.level, i.itemType')
            ->where('i.name LIKE :query')
            ->andWhere('i.itemType = :resourceType OR i.itemType IS NULL')
            ->setParameter('query', '%' . $query . '%')
            ->setParameter('resourceType', ItemType::RESOURCE)
            ->setMaxResults($limit)
            ->orderBy('i.name', 'ASC')
            ->getQuery()
            ->getArrayResult();
        
        $itemsData = array_map(function($item) {
            return [
                'id' => $item['id'],
                'name' => $item['name'],
                'level' => $item['level'] ?? null,
                'type' => 'Ressource',
                'display' => $item['name'] . ($item['level'] ? ' (Niv.' . $item['level'] . ')' : '')
            ];
        }, $items);
        
        return $this->json(['items' => $itemsData]);
    }
    
    private function getTypeLabel(?string $itemType): string
    {
        if (!$itemType) {
            return 'Divers';
        }
        
        return match($itemType) {
            'Resource' => 'Ressource',
            'Equipment' => 'Équipement', 
            'Consumable' => 'Consommable',
            default => 'Divers'
        };
    }
}