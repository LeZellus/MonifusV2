<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Response;

class ExportService
{
    public function exportSalesToCsv(array $sales): Response
    {
        $filename = 'ventes_dofus_' . date('Y-m-d') . '.csv';
        
        $response = new Response();
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        $output = fopen('php://temp', 'w');
        
        // En-têtes CSV
        fputcsv($output, [
            'Date Vente',
            'Item',
            'Catégorie',
            'Niveau',
            'Personnage',
            'Serveur',
            'Nombre Lots',
            'Unité Vente',
            'Prix Achat',
            'Prix Vente Prévu',
            'Prix Vente Réel',
            'Profit Réel',
            'Profit %',
            'Performance vs Prévu',
            'Notes'
        ], ';');

        // Données
        foreach ($sales as $sale) {
            $lotGroup = $sale->getLotGroup();
            $realizedProfit = $sale->getActualSellPrice() - $lotGroup->getBuyPricePerLot();
            $profitPercent = $lotGroup->getBuyPricePerLot() > 0 ? 
                ($realizedProfit / $lotGroup->getBuyPricePerLot() * 100) : 0;
            $performance = $sale->getActualSellPrice() - $lotGroup->getSellPricePerLot();

            fputcsv($output, [
                $sale->getSoldAt()->format('d/m/Y H:i'),
                $lotGroup->getItem()->getName(),
                $lotGroup->getItem()->getLevel(),
                $lotGroup->getDofusCharacter()->getName(),
                $lotGroup->getDofusCharacter()->getServer()->getName(),
                $lotGroup->getLotSize(),
                $lotGroup->getSaleUnit()->value,
                $lotGroup->getBuyPricePerLot(),
                $lotGroup->getSellPricePerLot(),
                $sale->getActualSellPrice(),
                $realizedProfit,
                round($profitPercent, 2),
                $performance,
                $sale->getNotes() ?? ''
            ], ';');
        }

        rewind($output);
        $response->setContent(stream_get_contents($output));
        fclose($output);

        return $response;
    }
}