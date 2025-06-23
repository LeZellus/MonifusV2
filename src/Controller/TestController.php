<?php
// src/Controller/TestController.php
namespace App\Controller;

use App\Repository\DofusCharacterRepository;
use App\Repository\LotGroupRepository;
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

    #[Route('/api/test/lotgroup-performance', name: 'api_test_lotgroup')]
    public function testLotGroupPerformance(
        DofusCharacterRepository $characterRepo,
        LotGroupRepository $lotGroupRepo,
        EntityManagerInterface $em
    ): Response {
        try {
            $character = $characterRepo->find(1);
            
            if (!$character) {
                return $this->json(['error' => 'Personnage ID 1 non trouvé']);
            }
            
            // Activer le debug SQL
            $logger = new \Doctrine\DBAL\Logging\DebugStack();
            $em->getConnection()->getConfiguration()->setSQLLogger($logger);
            
            // Test 1: Méthode optimisée (on sait que ça marche)
            $startTime = microtime(true);
            $lots = $lotGroupRepo->findByCharacterOptimized($character);
            $optimizedTime = (microtime(true) - $startTime) * 1000;
            
            // Test 2: Analytics simple SEULEMENT
            $analyticsStartTime = microtime(true);
            $analytics = $lotGroupRepo->getCharacterAnalytics($character);
            $analyticsTime = (microtime(true) - $analyticsStartTime) * 1000;
            
            return $this->json([
                'status' => '✅ SUCCESS - Toutes les méthodes OK',
                'character_name' => $character->getName(),
                'results' => [
                    'findByCharacterOptimized' => [
                        'time_ms' => round($optimizedTime, 2),
                        'lots_found' => count($lots),
                        'first_item' => count($lots) > 0 ? $lots[0]->getItem()->getName() : null
                    ],
                    'getCharacterAnalytics' => [
                        'time_ms' => round($analyticsTime, 2),
                        'total_lots' => $analytics['totalLots'],
                        'total_investment' => $analytics['totalInvestment']
                    ]
                ],
                'performance_summary' => [
                    'total_queries' => count($logger->queries),
                    'optimized_vs_N+1' => 'Optimisé évite ' . (count($lots) + 1) . ' requêtes!',
                    'sql_queries' => array_slice(array_map(fn($q) => $q['sql'], $logger->queries), 0, 5)
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'status' => '❌ ERROR',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => basename($e->getFile())
            ]);
        }
    }


    #[Route('/api/test/simple-lotgroup', name: 'api_test_simple_lotgroup')]
    public function testSimpleLotGroup(
        LotGroupRepository $lotGroupRepo,
        DofusCharacterRepository $characterRepo
    ): Response {
        try {
            // Prendre le premier personnage
            $character = $characterRepo->createQueryBuilder('c')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
            
            if (!$character) {
                return $this->json(['error' => 'Aucun personnage trouvé']);
            }
            
            // Test de la méthode optimisée seule
            $startTime = microtime(true);
            $results = $lotGroupRepo->findByCharacterOptimized($character);
            $time = (microtime(true) - $startTime) * 1000;
            
            return $this->json([
                'status' => '✅ Success',
                'character' => $character->getName(),
                'lots_found' => count($results),
                'execution_time_ms' => round($time, 2),
                'first_lot_item' => count($results) > 0 ? $results[0]->getItem()->getName() : 'Aucun lot'
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'status' => '❌ Error',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => basename($e->getFile())
            ]);
        }
    }
}