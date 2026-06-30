<?php

declare(strict_types=1);

namespace App\DataFixtures\Core;

use App\DataFixtures\Helper\FixtureReference;
use App\Entity\Organization;
use App\Entity\OrganizationUser;
use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Associe les utilisateurs aux organisations selon les scénarios métier.
 *
 * Dépend de :
 * - UserFixtures
 * - OrganizationFixtures
 *
 * Scénarios :
 * - Org 0 "Afridil Digital"  : admin(OWNER), manager(EDITOR), user-3(EDITOR), user-4(VIEWER), user-5(VIEWER)
 * - Org 1 "SkyMotion Prod"   : manager(OWNER), user-6(EDITOR), user-7(VIEWER)
 * - Org 2 "WebPulse Agency"  : user-8(OWNER), admin(ADMIN), user-9(EDITOR), user-10(EDITOR), user-11(VIEWER), user-12(VIEWER), user-13(VIEWER), user-14(VIEWER)
 * - Org 3 "Studio Freelance" : user(OWNER), user-3(VIEWER)
 */
final class OrganizationUserFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['core', 'demo', 'test'];
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            OrganizationFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        // Org 0 — Afridil Digital (PME en croissance)
        $this->addMember($manager, FixtureReference::ORG_AFRIDIL, FixtureReference::USER_ADMIN, UserRole::OWNER);
        $this->addMember($manager, FixtureReference::ORG_AFRIDIL, FixtureReference::USER_MANAGER, UserRole::EDITOR);
        $this->addMember($manager, FixtureReference::ORG_AFRIDIL, FixtureReference::user(3), UserRole::EDITOR);
        $this->addMember($manager, FixtureReference::ORG_AFRIDIL, FixtureReference::user(4), UserRole::VIEWER);
        $this->addMember($manager, FixtureReference::ORG_AFRIDIL, FixtureReference::user(5), UserRole::VIEWER);

        // Org 1 — SkyMotion Prod (Nouveau client)
        $this->addMember($manager, FixtureReference::ORG_SKYMOTION, FixtureReference::USER_MANAGER, UserRole::OWNER);
        $this->addMember($manager, FixtureReference::ORG_SKYMOTION, FixtureReference::user(6), UserRole::EDITOR);
        $this->addMember($manager, FixtureReference::ORG_SKYMOTION, FixtureReference::user(7), UserRole::VIEWER);

        // Org 2 — WebPulse Agency (Grande agence)
        $this->addMember($manager, FixtureReference::ORG_WEBPULSE, FixtureReference::user(8), UserRole::OWNER);
        $this->addMember($manager, FixtureReference::ORG_WEBPULSE, FixtureReference::USER_ADMIN, UserRole::ADMIN);
        $this->addMember($manager, FixtureReference::ORG_WEBPULSE, FixtureReference::user(9), UserRole::EDITOR);
        $this->addMember($manager, FixtureReference::ORG_WEBPULSE, FixtureReference::user(10), UserRole::EDITOR);
        $this->addMember($manager, FixtureReference::ORG_WEBPULSE, FixtureReference::user(11), UserRole::VIEWER);
        $this->addMember($manager, FixtureReference::ORG_WEBPULSE, FixtureReference::user(12), UserRole::VIEWER);
        $this->addMember($manager, FixtureReference::ORG_WEBPULSE, FixtureReference::user(13), UserRole::VIEWER);
        $this->addMember($manager, FixtureReference::ORG_WEBPULSE, FixtureReference::user(14), UserRole::VIEWER);

        // Org 3 — Studio Freelance (En difficulté)
        $this->addMember($manager, FixtureReference::ORG_FREELANCE, FixtureReference::USER_USER, UserRole::OWNER);
        $this->addMember($manager, FixtureReference::ORG_FREELANCE, FixtureReference::user(3), UserRole::VIEWER);

        $manager->flush();
    }

    private function addMember(ObjectManager $manager, string $orgRef, string $userRef, UserRole $role): void
    {
        /** @var Organization $org */
        $org = $this->getReference($orgRef, Organization::class);
        /** @var User $user */
        $user = $this->getReference($userRef, User::class);

        $orgUser = new OrganizationUser();
        $orgUser->setRole($role);
        $org->addOrganizationUser($orgUser);
        $user->addOrganizationUser($orgUser);

        $manager->persist($orgUser);
    }
}
