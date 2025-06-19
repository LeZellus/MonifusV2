<?php

namespace App\Command;

use App\Entity\Item;
use App\Service\DofusApiService;
use App\Enum\ItemType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fetch-dofus-items',
    description: 'Récupération des items Dofus',
)]
class FetchDofusItemsCommand extends Command
{
    public function __construct(
        private DofusApiService $dofusApiService,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('count', 'c', InputOption::VALUE_OPTIONAL, 'Nombre d\'items à récupérer', 10)
            ->addOption('skip', 's', InputOption::VALUE_OPTIONAL, 'Nombre d\'items à ignorer', 0);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $totalWanted = (int) $input->getOption('count');
        $startSkip = (int) $input->getOption('skip');
        
        // Désactiver le SQL logger
        $this->entityManager->getConnection()->getConfiguration()->setSQLLogger(null);
        
        $io->title("Récupération de {$totalWanted} items Dofus (à partir de {$startSkip})");
        
        $created = 0;
        $skipped = 0;
        $totalProcessed = 0;
        $currentSkip = $startSkip;
        $batchSize = 50;
        
        while ($totalProcessed < $totalWanted) {
            $remainingItems = $totalWanted - $totalProcessed;
            $currentBatchSize = min($batchSize, $remainingItems);
            
            $io->text("Batch skip={$currentSkip}, limit={$currentBatchSize} | Mémoire: " . round(memory_get_usage()/1024/1024, 2) . "MB");
            
            $items = $this->dofusApiService->fetchItems($currentSkip, $currentBatchSize);
            $itemsCount = count($items); // ← CALCULER AVANT DE MODIFIER $items
            
            if (empty($items)) {
                $io->warning("Fin de l'API atteinte à skip={$currentSkip}");
                break;
            }
            
            foreach ($items as $apiItem) {
                $ankamaId = (int) $apiItem['id'];
                
                // Vérifier s'il existe déjà
                $existingItem = $this->entityManager->getRepository(Item::class)
                    ->findOneBy(['ankamaId' => $ankamaId]);
                    
                if ($existingItem) {
                    $skipped++;
                    continue;
                }
                
                // Créer le nouvel item
                $item = new Item();
                $item->setAnkamaId($ankamaId);
                $item->setName($apiItem['name']['fr'] ?? $apiItem['name'] ?? 'Item sans nom');
                
                if (isset($apiItem['level'])) {
                    $item->setLevel($apiItem['level']);
                }
                
                if (isset($apiItem['img'])) {
                    $item->setImgUrl($apiItem['img']);
                }
                
                $this->entityManager->persist($item);
                $created++;
            }
            
            // Flush et clear
            $this->entityManager->flush();
            $this->entityManager->clear();
            
            // Libérer la mémoire
            unset($items);
            
            $totalProcessed += $itemsCount; // ← UTILISER LA VARIABLE SAUVEGARDÉE
            $currentSkip += $itemsCount;    // ← UTILISER LA VARIABLE SAUVEGARDÉE
            
            $io->text("Progress: {$totalProcessed}/{$totalWanted} | Créés: {$created} | Ignorés: {$skipped}");
            
            // Si on a récupéré moins d'items que demandé, on a atteint la fin
            if ($itemsCount < $currentBatchSize) {
                $io->warning("Fin de l'API atteinte");
                break;
            }
        }
        
        $io->success("TERMINÉ ! Items traités : {$created} créés, {$skipped} ignorés");
        
        return Command::SUCCESS;
    }
}