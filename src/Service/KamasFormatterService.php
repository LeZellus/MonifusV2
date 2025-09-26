<?php

namespace App\Service;

class KamasFormatterService
{
    /**
     * Format les kamas selon le style Dofus (sans HTML)
     */
    public function format(?int $amount): string
    {
        if ($amount === null) {
            return '-';
        }

        $abs = abs($amount);
        $sign = $amount < 0 ? '-' : '';

        // Format Dofus: 700 000 000k = 700kk (millions de milliers)
        if ($abs >= 700000000) {
            $kk = intval(floor($abs / 1000000));
            return $sign . $kk . 'kk';
        }

        // Format Dofus: 1 500 000k = 1m5k (millions + milliers)
        if ($abs >= 1000000) {
            $millions = intval(floor($abs / 1000000));
            $remainder = $abs % 1000000;
            $milliers = intval(floor($remainder / 100000)); // Centaines de milliers

            if ($milliers > 0) {
                return $sign . $millions . 'm' . $milliers;
            } else {
                return $sign . $millions . 'm';
            }
        }

        // Format standard: pas de "k" pour les milliers simples en affichage
        // Exemple: 1500 kamas = 1500 (pas 1.5k)
        if ($abs >= 1000) {
            return $sign . number_format($abs, 0, '', ' ');
        }

        return $sign . $abs;
    }

    /**
     * Format avec HTML pour les templates (avec couleurs)
     */
    public function formatWithHtml(?int $amount): string
    {
        if ($amount === null) {
            return '-';
        }

        $abs = abs($amount);
        $sign = $amount < 0 ? '-' : '';

        // Format Dofus: 700 000 000k = 700kk (millions de milliers)
        if ($abs >= 700000000) {
            $kk = intval(floor($abs / 1000000));
            return $sign . $kk . '<span class="text-orange-400">kk</span>';
        }

        // Format Dofus: 1 500 000k = 1m5k (millions + milliers)
        if ($abs >= 1000000) {
            $millions = intval(floor($abs / 1000000));
            $remainder = $abs % 1000000;
            $milliers = intval(floor($remainder / 100000)); // Centaines de milliers

            if ($milliers > 0) {
                return $sign . $millions . '<span class="text-orange-400">m</span>' . $milliers;
            } else {
                return $sign . $millions . '<span class="text-orange-400">m</span>';
            }
        }

        // Format standard: pas de "k" pour les milliers simples en affichage
        // Exemple: 1500 kamas = 1500 (pas 1.5k)
        if ($abs >= 1000) {
            return $sign . number_format($abs, 0, '', ' ');
        }

        return $sign . $abs;
    }
}