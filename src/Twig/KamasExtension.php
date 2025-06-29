<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class KamasExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('format_kamas', [$this, 'formatKamas'], ['is_safe' => ['html']]),
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
                $formatted = $millions . 'm' . $hundreds_thousands . '<span class="text-orange-400"> k</span>';
            } else {
                $formatted = $millions . 'm<span class="text-orange-400"> k</span>';
            }
            
            return ($amount < 0 ? '-' : '') . $formatted;
        }
        
        return number_format($amount, 0, ',', ' ') . '<span class="text-orange-400"> k</span>';
    }
}