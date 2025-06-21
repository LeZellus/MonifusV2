<?php

namespace App\Controller;

use App\Service\BackupService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/backup')]
#[IsGranted('ROLE_USER')]
class BackupController extends AbstractController
{
    #[Route('/', name: 'app_backup_index')]
    public function index(BackupService $backupService): Response
    {
        $summary = $backupService->generateDataSummary($this->getUser());
        
        return $this->render('backup/index.html.twig', [
            'summary' => $summary,
        ]);
    }

    #[Route('/export', name: 'app_backup_export')]
    public function export(BackupService $backupService): Response
    {
        return $backupService->exportUserData($this->getUser());
    }

    #[Route('/import', name: 'app_backup_import')]
    public function import(): Response
    {
        return $this->render('backup/import.html.twig');
    }

    #[Route('/import/process', name: 'app_backup_import_process', methods: ['POST'])]
    public function processImport(Request $request, BackupService $backupService): Response
    {
        /** @var UploadedFile $file */
        $file = $request->files->get('backup_file');
        
        if (!$file) {
            $this->addFlash('error', 'Aucun fichier sélectionné.');
            return $this->redirectToRoute('app_backup_import');
        }
        
        // Vérifications du fichier
        if ($file->getClientOriginalExtension() !== 'json') {
            $this->addFlash('error', 'Format de fichier invalide. Seuls les fichiers JSON sont acceptés.');
            return $this->redirectToRoute('app_backup_import');
        }
        
        if ($file->getSize() > 10 * 1024 * 1024) { // 10MB max
            $this->addFlash('error', 'Fichier trop volumineux (maximum 10MB).');
            return $this->redirectToRoute('app_backup_import');
        }
        
        try {
            // Lire et décoder le JSON
            $content = file_get_contents($file->getPathname());
            $data = json_decode($content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Fichier JSON invalide: ' . json_last_error_msg());
            }
            
            // Demander confirmation si l'utilisateur a déjà des données
            $existingSummary = $backupService->generateDataSummary($this->getUser());
            $hasExistingData = $existingSummary['profiles_count'] > 0;
            
            if ($hasExistingData && !$request->request->get('confirm_overwrite')) {
                return $this->render('backup/confirm_import.html.twig', [
                    'existing_summary' => $existingSummary,
                    'import_summary' => $this->getImportSummary($data),
                    'file_content' => base64_encode($content)
                ]);
            }
            
            // Procéder à l'import
            $stats = $backupService->importUserData($this->getUser(), $data);
            
            // Afficher les résultats
            if (empty($stats['errors'])) {
                $this->addFlash('success', 
                    "Import réussi ! " .
                    "Profils: {$stats['profiles_created']}, " .
                    "Personnages: {$stats['characters_created']}, " .
                    "Lots: {$stats['lots_created']}, " .
                    "Ventes: {$stats['sales_created']}, " .
                    "Surveillances: {$stats['watches_created']}"
                );
            } else {
                $this->addFlash('warning', 
                    "Import partiel. Éléments créés avec " . count($stats['errors']) . " erreur(s)."
                );
                foreach ($stats['errors'] as $error) {
                    $this->addFlash('error', $error);
                }
            }
            
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'import: ' . $e->getMessage());
        }
        
        return $this->redirectToRoute('app_backup_index');
    }

    #[Route('/import/confirm', name: 'app_backup_import_confirm', methods: ['POST'])]
    public function confirmImport(Request $request, BackupService $backupService): Response
    {
        $fileContent = $request->request->get('file_content');
        if (!$fileContent) {
            $this->addFlash('error', 'Données manquantes.');
            return $this->redirectToRoute('app_backup_import');
        }
        
        try {
            $content = base64_decode($fileContent);
            $data = json_decode($content, true);
            
            $stats = $backupService->importUserData($this->getUser(), $data);
            
            if (empty($stats['errors'])) {
                $this->addFlash('success', "Import confirmé et réussi !");
            } else {
                $this->addFlash('warning', "Import réalisé avec quelques erreurs.");
                foreach ($stats['errors'] as $error) {
                    $this->addFlash('error', $error);
                }
            }
            
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur: ' . $e->getMessage());
        }
        
        return $this->redirectToRoute('app_backup_index');
    }

    private function getImportSummary(array $data): array
    {
        return [
            'profiles_count' => count($data['profiles'] ?? []),
            'characters_count' => count($data['characters'] ?? []),
            'lots_count' => count($data['lots'] ?? []),
            'sales_count' => count($data['sales'] ?? []),
            'watches_count' => count($data['market_watch'] ?? [])
        ];
    }
}