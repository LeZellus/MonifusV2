<?php

namespace App\DataFixtures;

use App\Entity\Classe;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ClasseFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $classes = [
            ['name' => 'Iop', 'img_url' => 'classes/iop.png'],
            ['name' => 'Cra', 'img_url' => 'classes/cra.png'],
            ['name' => 'Eniripsa', 'img_url' => 'classes/eniripsa.png'],
            ['name' => 'Ecaflip', 'img_url' => 'classes/ecaflip.png'],
            ['name' => 'Enutrof', 'img_url' => 'classes/enutrof.png'],
            ['name' => 'Sram', 'img_url' => 'classes/sram.png'],
            ['name' => 'Xelor', 'img_url' => 'classes/xelor.png'],
            ['name' => 'Féca', 'img_url' => 'classes/feca.png'],
            ['name' => 'Osamodas', 'img_url' => 'classes/osamodas.png'],
            ['name' => 'Sadida', 'img_url' => 'classes/sadida.png'],
            ['name' => 'Huppermage', 'img_url' => 'classes/huppermage.png'],
            ['name' => 'Steamer', 'img_url' => 'classes/steamer.png'],
            ['name' => 'Forgelance', 'img_url' => 'classes/forgelance.png'],
            ['name' => 'Pandawa', 'img_url' => 'classes/pandawa.png'],
            ['name' => 'Eliotrope', 'img_url' => 'classes/eliotrope.png'],
            ['name' => 'Zobal', 'img_url' => 'classes/zobal.png'],
            ['name' => 'Roublard', 'img_url' => 'classes/roublard.png'],
            ['name' => 'Ouginak', 'img_url' => 'classes/ouginak.png'],
            ['name' => 'Sacrieur', 'img_url' => 'classes/sacrieur.png'],
        ];

        foreach ($classes as $index => $classeData) {
            $classe = new Classe();
            $classe->setName($classeData['name']);
            $classe->setImgUrl($classeData['img_url']);
            
            $manager->persist($classe);
            $this->addReference('classe_' . $index, $classe);
        }

        $manager->flush();
    }
}