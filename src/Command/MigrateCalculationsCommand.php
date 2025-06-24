<?php
/**
 * Script de migration automatique pour centraliser les calculs
 * À exécuter via: php bin/console app:migrate-calculations
 */

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: 'app:migrate-calculations',
    description: 'Migre automatiquement les calculs vers le service centralisé'
)]
class MigrateCalculationsCommand extends Command
{
    private array $replacements = [];
    private array $backupFiles = [];

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('🔄 Migration des calculs vers le service centralisé');
        
        // Étape 1 : Sauvegardes
        $io->section('📦 Création des sauvegardes...');
        $this->createBackups($io);
        
        // Étape 2 : Migration des templates Twig
        $io->section('🎨 Migration des templates Twig...');
        $this->migrateTwigTemplates($io);
        
        // Étape 3 : Migration des contrôleurs JavaScript
        $io->section('⚡ Migration des contrôleurs JavaScript...');
        $this->migrateJavaScriptControllers($io);
        
        // Étape 4 : Validation
        $io->section('✅ Validation des changements...');
        $this->validateChanges($io);
        
        $io->success('Migration terminée avec succès !');
        $io->note('Pensez à tester manuellement toutes les fonctionnalités.');
        
        return Command::SUCCESS;
    }

    private function createBackups(SymfonyStyle $io): void
    {
        $filesToBackup = [
            'assets/controllers/profit_calculator_controller.js',
            'assets/controllers/sale_calculator_controller.js',
            'templates/components/profit_display.html.twig',
            'templates/components/price_display.html.twig'
        ];

        foreach ($filesToBackup as $file) {
            if (file_exists($file)) {
                $backupFile = $file . '.backup-' . date('Y-m-d-H-i-s');
                copy($file, $backupFile);
                $this->backupFiles[] = $backupFile;
                $io->text("✅ Sauvegarde: {$file} → {$backupFile}");
            }
        }
    }

    private function migrateTwigTemplates(SymfonyStyle $io): void
    {
        $finder = new Finder();
        $finder->files()->in('templates')->name('*.html.twig');

        $patterns = [
            // Remplacement des calculs manuels
            '/\{\%\s*set\s+profit_per_unit\s*=\s*sell_price\s*-\s*buy_price\s*\%\}/' => 
                '',
            '/\{\%\s*set\s+total_profit\s*=\s*profit_per_unit\s*\*\s*\(quantity\|default\(1\)\)\s*\%\}/' => 
                '',
            
            // Remplacement des includes profit_display
            '/\{\%\s*include\s+[\'"]components\/profit_display\.html\.twig[\'"]/' => 
                '{% include \'components/unified_profit_display.html.twig\'',
                
            // Remplacement du formatage manuel
            '/\{\{\s*\(\w+\s*\/\s*1000\)\|number_format\(0,\s*\',\',\s*\'\s*\'\)\s*\~\s*\'k\'\s*\}\}/' => 
                '{{ format_kamas($1) }}',
        ];

        foreach ($finder as $file) {
            $content = file_get_contents($file->getRealPath());
            $originalContent = $content;
            
            foreach ($patterns as $pattern => $replacement) {
                $content = preg_replace($pattern, $replacement, $content);
            }
            
            // Remplacements spécifiques par template
            $content = $this->applyTemplateSpecificReplacements($file->getFilename(), $content);
            
            if ($content !== $originalContent) {
                file_put_contents($file->getRealPath(), $content);
                $io->text("✅ Migré: {$file->getRelativePathname()}");
            }
        }
    }

    private function applyTemplateSpecificReplacements(string $filename, string $content): string
    {
        switch ($filename) {
            case '_table_row.html.twig':
                // Simplification des conditions complexes
                $content = str_replace(
                    '{% include \'components/profit_display.html.twig\' with {
        buy_price: item.buyPricePerLot,
        sell_price: lastSale.actualSellPrice,
        quantity: lastSale.quantitySold,
        color: \'white\'
    } %}',
                    '{% include \'components/unified_profit_display.html.twig\' with {
        lot_unit: lastSale,
        mode: \'compact\'
    } %}',
                    $content
                );
                break;
                
            case 'index.html.twig':
                if (str_contains($content, 'analytics')) {
                    // Remplacement des calculs de ROI manuels
                    $content = str_replace(
                        '{% set roi = stats.global.investedAmount > 0 ? (totalProfit / stats.global.investedAmount * 100) : 0 %}',
                        '{% set roi = calculate_roi(stats.global.investedAmount, totalProfit) %}',
                        $content
                    );
                }
                break;
        }
        
        return $content;
    }

    private function migrateJavaScriptControllers(SymfonyStyle $io): void
    {
        $jsFiles = [
            'templates/lot/_form.html.twig',
            'templates/lot_sale/sell.html.twig'
        ];

        foreach ($jsFiles as $file) {
            if (!file_exists($file)) continue;
            
            $content = file_get_contents($file);
            $originalContent = $content;
            
            // Remplacement des attributs data-controller
            $content = str_replace(
                'data-controller="profit-calculator"',
                'data-controller="centralized-calculator" data-centralized-calculator-mode-value="lot"',
                $content
            );
            
            $content = str_replace(
                'data-controller="sale-calculator"',
                'data-controller="centralized-calculator" data-centralized-calculator-mode-value="sale"',
                $content
            );
            
            // Remplacement des targets
            $content = str_replace(
                'data-profit-calculator-target=',
                'data-centralized-calculator-target=',
                $content
            );
            
            $content = str_replace(
                'data-sale-calculator-target=',
                'data-centralized-calculator-target=',
                $content
            );
            
            // Remplacement des actions
            $content = str_replace(
                'data-action="input->profit-calculator#updateProfit"',
                'data-action="input->centralized-calculator#updateCalculations"',
                $content
            );
            
            $content = str_replace(
                'data-action="input->sale-calculator#updatePreview"',
                'data-action="input->centralized-calculator#updateCalculations"',
                $content
            );
            
            if ($content !== $originalContent) {
                file_put_contents($file, $content);
                $io->text("✅ JS migré: {$file}");
            }
        }
    }

    private function validateChanges(SymfonyStyle $io): void
    {
        $checkFiles = [
            'src/Service/ProfitCalculatorService.php' => 'Service principal',
            'src/Twig/ProfitCalculatorExtension.php' => 'Extension Twig',
            'assets/controllers/centralized_calculator_controller.js' => 'Contrôleur JS',
            'templates/components/unified_profit_display.html.twig' => 'Template unifié'
        ];

        $allOk = true;
        foreach ($checkFiles as $file => $description) {
            if (file_exists($file)) {
                $io->text("✅ {$description}: OK");
            } else {
                $io->error("❌ {$description}: MANQUANT ({$file})");
                $allOk = false;
            }
        }

        if (!$allOk) {
            $io->warning('Certains fichiers sont manquants. Créez-les manuellement avant de continuer.');
        }

        // Vérification de la syntaxe Twig
        $io->text('🔍 Vérification de la syntaxe Twig...');
        $finder = new Finder();
        $finder->files()->in('templates')->name('*.html.twig');
        
        $syntaxErrors = 0;
        foreach ($finder as $file) {
            $content = file_get_contents($file->getRealPath());
            
            // Vérifications basiques
            if (substr_count($content, '{%') !== substr_count($content, '%}')) {
                $io->error("❌ Syntaxe Twig invalide: {$file->getRelativePathname()}");
                $syntaxErrors++;
            }
        }
        
        if ($syntaxErrors === 0) {
            $io->text('✅ Syntaxe Twig: OK');
        }
    }
}