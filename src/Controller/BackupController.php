<?php

namespace App\Controller;

use App\Service\BackupService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
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
}