<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class KamasExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('format_kamas', [$this, 'formatKamas']),
        ];
    }

    public function formatKamas(?int $amount): string
    {
        if ($amount === null) {
            return '-';
        }

        if (abs($amount) >= 1000000) {
            $millions = intval(floor(abs($amount) / 1000000));
            $remainder = abs($amount) % 1000000;
            $hundreds_thousands = intval(floor($remainder / 100000));
            
            if ($hundreds_thousands > 0) {
                $formatted = $millions . 'm' . $hundreds_thousands . ' k';
            } else {
                $formatted = $millions . 'm k';
            }
            
            return ($amount < 0 ? '-' : '') . $formatted;
        }
        
        return number_format($amount, 0, ',', ' ') . ' k';
    }
}