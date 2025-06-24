<?php

namespace App\Command;

use App\Entity\Item;
use App\Enum\ItemType;
use App\Service\DofusApiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fix-item-types',
    description: 'Corrige les types d\'items existants via l\'API Dofus',
)]
class FixItemTypesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Mode simulation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $isDryRun = $input->getOption('dry-run');
        
        $io->title('🚀 Correction INSTANTANÉE des types (SQL direct)');
        
        $totalUpdated = 0;
        
        // 1. ÉQUIPEMENTS - Une seule requête SQL pour tous
        $io->text('⚔️  Correction des équipements...');
        
        $equipmentPatterns = [
            '%dofus%', '%épée%', '%dague%', '%arc%', '%baton%', '%bâton%', '%marteau%', 
            '%hache%', '%pelle%', '%pioche%', '%faux%', '%baguette%', '%amulette%',
            '%chapeau%', '%coiffe%', '%casque%', '%plastron%', '%armure%',
            '%cape%', '%ceinture%', '%botte%', '%anneau%', '%collier%', '%bouclier%',
            '%solomonk%', '%légende%', '%legende%', '%dame jhessica%', '%gelano%',
            '%vulbis%', '%krtek%', '%trank%', '%abraknyde%', '%relique%', '%trophée%'
        ];
        
        $count = $this->massUpdateByPatterns($equipmentPatterns, ItemType::EQUIPMENT, $isDryRun);
        $totalUpdated += $count;
        $io->text("✅ {$count} équipements mis à jour");
        
        // 2. CONSOMMABLES
        $io->text('🍎 Correction des consommables...');
        
        $consumablePatterns = [
            '%potion%', '%philtre%', '%pain%', '%viande%', '%poisson grillé%',
            '%bonbon%', '%friandise%', '%gâteau%', '%tarte%', '%soupe%',
            '%boisson%', '%bière%', '%cidre%', '%vin%', '%lait%',
            '%rappel%', '%téléportation%', '%parchemin%'
        ];
        
        $count = $this->massUpdateByPatterns($consumablePatterns, ItemType::CONSUMABLE, $isDryRun);
        $totalUpdated += $count;
        $io->text("✅ {$count} consommables mis à jour");
        
        // 3. RESSOURCES
        $io->text('🔨 Correction des ressources...');
        
        $resourcePatterns = [
            '%minerai%', '%alliage%', '%bois de%', '%planche%', '%écorce%',
            '%fleur%', '%graine%', '%poudre%', '%essence%', '%huile%',
            '%cuir%', '%laine%', '%étoffe%', '%fil%', '%poil%', '%plume%',
            '%pierre%', '%gemme%', '%cristal%', '%rune %', '%queue de%',
            '%oeuf%', '%œuf%', '%coquille%', '%carapace%', '%dent%',
            '%frêne%', '%châtaignier%', '%chêne%', '%bambou%', '%orme%',
            '%fer%', '%cuivre%', '%bronze%', '%argent%', '%or%', '%cobalt%'
        ];
        
        $count = $this->massUpdateByPatterns($resourcePatterns, ItemType::RESOURCE, $isDryRun);
        $totalUpdated += $count;
        $io->text("✅ {$count} ressources mis à jour");
        
        // 4. Corrections spéciales par nom exact
        $io->text('⭐ Correction des items spéciaux...');
        
        $specialItems = [
            'Dofus Emeraude' => ItemType::EQUIPMENT,
            'Dofus Pourpre' => ItemType::EQUIPMENT,
            'Dofus Turquoise' => ItemType::EQUIPMENT,
            'Dofus Ocre' => ItemType::EQUIPMENT,
            'Dofus Vulbis' => ItemType::EQUIPMENT,
            'Solomonk' => ItemType::EQUIPMENT,
            'La Légende de Dame Jhessica' => ItemType::EQUIPMENT,
            'Gelano' => ItemType::EQUIPMENT,
            'Krtek' => ItemType::EQUIPMENT,
            'Trank' => ItemType::EQUIPMENT,
        ];
        
        $count = $this->massUpdateSpecialItems($specialItems, $isDryRun);
        $totalUpdated += $count;
        $io->text("✅ {$count} items spéciaux mis à jour");
        
        if ($isDryRun) {
            $io->info("DRY-RUN: {$totalUpdated} items auraient été mis à jour");
        } else {
            $io->success("🎉 {$totalUpdated} items mis à jour en quelques secondes !");
        }
        
        return Command::SUCCESS;
    }
    
    private function massUpdateByPatterns(array $patterns, ItemType $newType, bool $isDryRun): int
    {
        $totalUpdated = 0;
        
        foreach ($patterns as $pattern) {
            if ($isDryRun) {
                // Compter seulement en mode dry-run
                $count = (int) $this->entityManager->createQueryBuilder()
                    ->select('COUNT(i.id)')
                    ->from(Item::class, 'i')
                    ->where('LOWER(i.name) LIKE LOWER(:pattern)')
                    ->andWhere('(i.itemType != :newType OR i.itemType IS NULL)')
                    ->setParameter('pattern', $pattern)
                    ->setParameter('newType', $newType)
                    ->getQuery()
                    ->getSingleScalarResult();
            } else {
                // Mise à jour réelle
                $count = $this->entityManager->createQueryBuilder()
                    ->update(Item::class, 'i')
                    ->set('i.itemType', ':newType')
                    ->where('LOWER(i.name) LIKE LOWER(:pattern)')
                    ->andWhere('(i.itemType != :newType OR i.itemType IS NULL)')
                    ->setParameter('newType', $newType)
                    ->setParameter('pattern', $pattern)
                    ->getQuery()
                    ->execute();
            }
            
            $totalUpdated += $count;
        }
        
        return $totalUpdated;
    }
    
    private function massUpdateSpecialItems(array $specialItems, bool $isDryRun): int
    {
        $totalUpdated = 0;
        
        foreach ($specialItems as $itemName => $newType) {
            if ($isDryRun) {
                $count = (int) $this->entityManager->createQueryBuilder()
                    ->select('COUNT(i.id)')
                    ->from(Item::class, 'i')
                    ->where('i.name = :itemName')
                    ->andWhere('(i.itemType != :newType OR i.itemType IS NULL)')
                    ->setParameter('itemName', $itemName)
                    ->setParameter('newType', $newType)
                    ->getQuery()
                    ->getSingleScalarResult();
            } else {
                $count = $this->entityManager->createQueryBuilder()
                    ->update(Item::class, 'i')
                    ->set('i.itemType', ':newType')
                    ->where('i.name = :itemName')
                    ->andWhere('(i.itemType != :newType OR i.itemType IS NULL)')
                    ->setParameter('itemName', $itemName)
                    ->setParameter('newType', $newType)
                    ->getQuery()
                    ->execute();
            }
            
            $totalUpdated += $count;
        }
        
        return $totalUpdated;
    }
}