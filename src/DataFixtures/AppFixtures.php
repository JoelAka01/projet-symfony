<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AppFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $this->createUser($manager, 'admin@example.com', 'Admin', 'Demo', UserRole::ADMIN);
        $this->createUser($manager, 'manager@example.com', 'Manager', 'Demo', UserRole::EDITOR);
        $this->createUser($manager, 'user@example.com', 'User', 'Demo', UserRole::VIEWER);

        $manager->flush();
    }

    private function createUser(
        ObjectManager $manager,
        string $email,
        string $firstName,
        string $lastName,
        UserRole $role,
    ): void {
        $user = new User();
        $user
            ->setEmail($email)
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setRole($role)
            ->setIsVerified(true);

        $user->setPasswordHash($this->passwordHasher->hashPassword($user, 'password'));

        $manager->persist($user);
    }
}
