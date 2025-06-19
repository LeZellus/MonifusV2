<?php

namespace App\DataFixtures;

use App\Entity\Item;
use App\Enum\ItemType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ItemFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $items = [
            // Ressources
            ['name' => 'Bois de Frêne', 'type' => ItemType::RESOURCE, 'level' => 1],
            ['name' => 'Bois de Châtaignier', 'type' => ItemType::RESOURCE, 'level' => 10],
            ['name' => 'Bois de Noyer', 'type' => ItemType::RESOURCE, 'level' => 20],
            ['name' => 'Fer', 'type' => ItemType::RESOURCE, 'level' => 1],
            ['name' => 'Cuivre', 'type' => ItemType::RESOURCE, 'level' => 10],
            ['name' => 'Bronze', 'type' => ItemType::RESOURCE, 'level' => 20],
            
            // Équipements
            ['name' => 'Dofus Emeraude', 'type' => ItemType::EQUIPMENT, 'level' => 100],
            ['name' => 'Dofus Pourpre', 'type' => ItemType::EQUIPMENT, 'level' => 100],
            ['name' => 'Épée du Bouftou', 'type' => ItemType::EQUIPMENT, 'level' => 15],
            ['name' => 'Arc de Boisaille', 'type' => ItemType::EQUIPMENT, 'level' => 25],
            
            // Consommables
            ['name' => 'Pain', 'type' => ItemType::CONSUMABLE, 'level' => 1],
            ['name' => 'Potion de Vie Majeure', 'type' => ItemType::CONSUMABLE, 'level' => 50],
        ];

        foreach ($items as $index => $itemData) {
            $item = new Item();
            $item->setName($itemData['name']);
            $item->setItemType($itemData['type']);
            $item->setLevel($itemData['level']);
            
            $manager->persist($item);
            $this->addReference('item_' . $index, $item);
        }

        $manager->flush();
    }
}