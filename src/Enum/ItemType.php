<?php

namespace App\Enum;

enum ItemType: string
{
    case RESOURCE = 'Ressource';
    case EQUIPMENT = 'Equipement';
    case CONSUMABLE = 'Consommable';
}
