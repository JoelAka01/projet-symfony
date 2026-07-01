<?php

declare(strict_types=1);

namespace App\DataFixtures\Core;

use App\DataFixtures\Factory\UserFactory;
use App\DataFixtures\Helper\FixtureConfig;
use App\DataFixtures\Helper\FixtureHelper;
use App\DataFixtures\Helper\FixtureReference;
use App\Enum\UserRole;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Génère les utilisateurs de démonstration.
 *
 * Dépend de : rien
 *
 * Références créées :
 * - user-admin, user-manager, user-user (comptes démo)
 * - user-0 à user-14 (tous les utilisateurs par index)
 */
final class UserFixtures extends Fixture implements FixtureGroupInterface
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {}

    public static function getGroups(): array
    {
        return ['core', 'demo', 'test'];
    }

    public function load(ObjectManager $manager): void
    {
        $faker = FixtureHelper::faker();
        $index = 0;

        // ── Comptes démo ───────────────────────────────────────────────
        $admin = UserFactory::create($manager, $this->passwordHasher, 'admin@example.com', 'Admin', 'Demo', UserRole::ADMIN);
        $this->addReference(FixtureReference::USER_ADMIN, $admin);
        $this->addReference(FixtureReference::user($index++), $admin);

        $managerUser = UserFactory::create($manager, $this->passwordHasher, 'manager@example.com', 'Manager', 'Demo', UserRole::EDITOR);
        $this->addReference(FixtureReference::USER_MANAGER, $managerUser);
        $this->addReference(FixtureReference::user($index++), $managerUser);

        $user = UserFactory::create($manager, $this->passwordHasher, 'user@example.com', 'User', 'Demo', UserRole::VIEWER);
        $this->addReference(FixtureReference::USER_USER, $user);
        $this->addReference(FixtureReference::user($index++), $user);

        // ── Utilisateurs Faker ─────────────────────────────────────────
        $roles = [UserRole::EDITOR, UserRole::VIEWER];

        for ($i = FixtureConfig::DEMO_USERS; $i < FixtureConfig::USERS; $i++) {
            $fakerUser = UserFactory::create(
                $manager,
                $this->passwordHasher,
                sprintf('user%d@example.com', $i),
                $faker->firstName(),
                $faker->lastName(),
                $faker->randomElement($roles),
                $faker->boolean(85), // 85% vérifiés
            );

            $this->addReference(FixtureReference::user($index++), $fakerUser);
        }

        $manager->flush();
    }
}
