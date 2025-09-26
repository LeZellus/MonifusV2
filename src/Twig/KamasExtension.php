<?php

namespace App\Twig;

use App\Service\KamasFormatterService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class KamasExtension extends AbstractExtension
{
    public function __construct(
        private KamasFormatterService $kamasFormatterService
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('format_kamas', [$this, 'formatKamas'], ['is_safe' => ['html']]),
        ];
    }

    public function formatKamas(?int $amount): string
    {
        return $this->kamasFormatterService->formatWithHtml($amount);
    }
}