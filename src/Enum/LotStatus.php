<?php

namespace App\Enum;

enum LotStatus: string
{
    case AVAILABLE = 'Actif';
    case SOLD = 'Vendu';
}
