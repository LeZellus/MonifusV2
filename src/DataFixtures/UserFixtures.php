<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Utilisateur admin
        $admin = new User();
        $admin->setEmail('admin@test.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPseudonymeWebsite('Admin');
        $admin->setPseudonymeDofus('AdminDofus');
        $admin->setIsVerified(true);
        $admin->setIsTutorial(false);
        
        $hashedPassword = $this->passwordHasher->hashPassword($admin, 'password');
        $admin->setPassword($hashedPassword);
        
        $manager->persist($admin);
        $this->addReference('user_admin', $admin);

        // Utilisateur test
        $user = new User();
        $user->setEmail('user@test.com');
        $user->setPseudonymeWebsite('TestUser');
        $user->setPseudonymeDofus('TestDofus');
        $user->setIsVerified(true);
        $user->setIsTutorial(true);
        
        $hashedPassword = $this->passwordHasher->hashPassword($user, 'password');
        $user->setPassword($hashedPassword);
        
        $manager->persist($user);
        $this->addReference('user_test', $user);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [ClasseFixtures::class];
    }
}