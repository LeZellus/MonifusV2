<?php

namespace App\Enum;

enum SaleUnit: int
{
    case UNIT = 1;
    case TEN = 10;
    case HUNDRED = 100;
    case THOUSAND = 1000;

    public function getLabel(): string
    {
        return match($this) {
            self::UNIT => 'À l\'unité (x1)',
            self::TEN => 'Par 10 (x10)',
            self::HUNDRED => 'Par 100 (x100)',
            self::THOUSAND => 'Par 1000 (x1000)',
        };
    }
}