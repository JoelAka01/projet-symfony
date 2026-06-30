<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserFactory
{
    public static function create(
        ObjectManager $manager,
        UserPasswordHasherInterface $passwordHasher,
        string $email,
        string $firstName,
        string $lastName,
        UserRole $role = UserRole::VIEWER,
        bool $isVerified = true,
        string $password = 'password',
    ): User {
        $user = new User();
        $user
            ->setEmail($email)
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setRole($role)
            ->setIsVerified($isVerified);

        $user->setPasswordHash($passwordHasher->hashPassword($user, $password));

        $manager->persist($user);

        return $user;
    }
}
