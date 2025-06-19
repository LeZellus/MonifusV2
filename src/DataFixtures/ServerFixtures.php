<?php

namespace App\DataFixtures;

use App\Entity\Server;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ServerFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $servers = [
            ['name' => 'Salar', 'community' => 'FR'],
            ['name' => 'TalKasha', 'community' => 'INT'],
            ['name' => 'Tylezia', 'community' => 'FR'],
            ['name' => 'Ombre', 'community' => 'INT'],
            ['name' => 'Salar', 'community' => 'FR'],
            ['name' => 'Rafal', 'community' => 'INT'],
            ['name' => 'Orukam', 'community' => 'FR'],
            ['name' => 'Imagiro', 'community' => 'FR'],
            ['name' => 'HellMina', 'community' => 'FR'],
            ['name' => 'Brial', 'community' => 'FR'],
            ['name' => 'Mikhal', 'community' => 'FR'],
            ['name' => 'Kourial', 'community' => 'FR'],
            ['name' => 'Draconiros', 'community' => 'INT'],
            ['name' => 'Dakal', 'community' => 'FR'],
        ];

        foreach ($servers as $index => $serverData) {
            $server = new Server();
            $server->setName($serverData['name']);
            $server->setCommunity($serverData['community']);
            
            $manager->persist($server);
            $this->addReference('server_' . $index, $server);
        }

        $manager->flush();
    }
}