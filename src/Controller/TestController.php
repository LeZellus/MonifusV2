<?php
// src/Controller/TestController.php
namespace App\Controller;

use App\Repository\DofusCharacterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TestController extends AbstractController
{
    #[Route('/test/performance', name: 'test_performance')]
    public function testPerformance(
        DofusCharacterRepository $characterRepo,
        EntityManagerInterface $em
    ): Response {
        $startTime = microtime(true);
        $startQueries = 0;
        
        // Activer le compteur de requêtes
        $logger = new \Doctrine\DBAL\Logging\DebugStack();
        $em->getConnection()->getConfiguration()->setSQLLogger($logger);
        
        // ===== TEST AVANT OPTIMISATION =====
        $characters = $characterRepo->findAll();
        
        foreach ($characters as $character) {
            // Ceci va déclencher des requêtes sans EXTRA_LAZY
            $lotCount = $character->getLotGroups()->count();
            $watchCount = $character->getMarketWatches()->count();
            
            echo "Character: {$character->getName()} - Lots: {$lotCount}, Watches: {$watchCount}<br>";
        }
        
        $endTime = microtime(true);
        $totalQueries = count($logger->queries);
        $executionTime = ($endTime - $startTime) * 1000; // en ms
        
        return new Response(sprintf(
            '<h1>Test Performance</h1>
            <p><strong>Temps d\'exécution:</strong> %.2f ms</p>
            <p><strong>Nombre de requêtes:</strong> %d</p>
            <h2>Requêtes SQL:</h2>
            <pre>%s</pre>',
            $executionTime,
            $totalQueries,
            implode("\n\n", array_map(fn($q) => $q['sql'], $logger->queries))
        ));
    }

    #[Route('/api/test/performance', name: 'api_test_performance')]
    public function apiTestPerformance(
        DofusCharacterRepository $characterRepo,
        EntityManagerInterface $em
    ): Response {
        // ✅ Augmenter la limite mémoire temporairement
        ini_set('memory_limit', '512M');
        
        $startTime = microtime(true);
        
        // Debug SQL
        $logger = new \Doctrine\DBAL\Logging\DebugStack();
        $em->getConnection()->getConfiguration()->setSQLLogger($logger);
        
        // ✅ LIMITE à 3 personnages max pour éviter l'explosion mémoire
        $characters = $characterRepo->createQueryBuilder('dc')
            ->setMaxResults(3)
            ->getQuery()
            ->getResult();
        
        $results = [];
        $totalLots = 0;
        
        foreach ($characters as $character) {
            // ✅ Test simple : juste le count (pas de chargement des entités)
            $lotCount = $character->getLotGroups()->count();
            $totalLots += $lotCount;
            
            $results[] = [
                'character' => $character->getName(),
                'lots_count' => $lotCount
            ];
            
            // ✅ Libérer la mémoire après chaque personnage
            $em->detach($character);
        }
        
        $endTime = microtime(true);
        
        // ✅ Nettoyer complètement
        $em->clear();
        gc_collect_cycles();
        
        return $this->json([
            'status' => '✅ Test réussi',
            'memory_used_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'execution_time_ms' => round(($endTime - $startTime) * 1000, 2),
            'total_queries' => count($logger->queries),
            'characters_tested' => count($characters),
            'total_lots_found' => $totalLots,
            'sql_queries' => array_slice(array_map(fn($q) => $q['sql'], $logger->queries), 0, 10), // Max 10 requêtes affichées
            'character_results' => $results,
            'note' => 'Test limité à 3 personnages pour éviter l\'explosion mémoire'
        ]);
    }
}