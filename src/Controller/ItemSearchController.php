<?php

namespace App\Controller;

use App\Repository\ItemRepository;
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
        $query = $request->query->get('q', '');
        $limit = min((int) $request->query->get('limit', 20), 50);
        
        if (strlen($query) < 2) {
            return $this->json(['items' => []]);
        }
        
        $items = $itemRepository->createQueryBuilder('i')
            ->where('i.name LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->setMaxResults($limit)
            ->orderBy('i.name', 'ASC')
            ->getQuery()
            ->getResult();
        
        $itemsData = [];
        foreach ($items as $item) {
            $itemsData[] = [
                'id' => $item->getId(),
                'name' => $item->getName(),
                'level' => $item->getLevel(),
                'type' => $item->getItemType()?->value
            ];
        }
        
        return $this->json(['items' => $itemsData]);
    }
}