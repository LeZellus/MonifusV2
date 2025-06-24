<?php

namespace App\Twig;

use App\Service\ProfitCalculatorService;
use App\Entity\LotGroup;
use App\Entity\LotUnit;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ProfitCalculatorExtension extends AbstractExtension
{
    public function __construct(
        private ProfitCalculatorService $calculator
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('calculate_profit', [$this, 'calculateProfit']),
            new TwigFunction('calculate_investment', [$this, 'calculateInvestment']),
            new TwigFunction('calculate_roi', [$this, 'calculateROI']),
            new TwigFunction('format_kamas', [$this, 'formatKamas']),
            new TwigFunction('profit_class', [$this, 'getProfitClass']),
            new TwigFunction('roi_color', [$this, 'getROIColor']),
            new TwigFunction('lot_metrics', [$this, 'getLotMetrics']),
            new TwigFunction('sale_metrics', [$this, 'getSaleMetrics'])
        ];
    }

    /**
     * Calcule le profit total : (prix_vente - prix_achat) * quantité
     */
    public function calculateProfit(int $sellPrice, int $buyPrice, int $quantity = 1): int
    {
        return $this->calculator->calculateTotalProfit($quantity, $sellPrice, $buyPrice);
    }

    /**
     * Calcule l'investissement : prix_achat * quantité
     */
    public function calculateInvestment(int $buyPrice, int $quantity): int
    {
        return $this->calculator->calculateInvestment($quantity, $buyPrice);
    }

    /**
     * Calcule le ROI en pourcentage
     */
    public function calculateROI(int $buyPrice, int $sellPrice): float
    {
        return $this->calculator->calculateROI($buyPrice, $sellPrice);
    }

    /**
     * Formate un montant en kamas (ex: 1500000 → 1.5M)
     */
    public function formatKamas(int $amount): string
    {
        return $this->calculator->formatKamas($amount);
    }

    /**
     * Retourne la classe CSS pour un profit (vert/rouge)
     */
    public function getProfitClass(int $profit): string
    {
        return $this->calculator->getProfitClass($profit);
    }

    /**
     * Retourne la couleur selon le ROI
     */
    public function getROIColor(float $roi): string
    {
        return $this->calculator->getROIColor($roi);
    }

    /**
     * Récupère toutes les métriques d'un LotGroup
     */
    public function getLotMetrics(LotGroup $lotGroup): array
    {
        return $this->calculator->calculateLotGroupMetrics($lotGroup);
    }

    /**
     * Récupère toutes les métriques d'une vente
     */
    public function getSaleMetrics(LotUnit $lotUnit): array
    {
        return $this->calculator->calculateSaleMetrics($lotUnit);
    }
}