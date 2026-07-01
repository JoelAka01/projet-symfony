<?php

declare(strict_types=1);

namespace App\DataFixtures\Project;

use App\DataFixtures\Core\OrganizationUserFixtures;
use App\DataFixtures\Factory\ProjectFactory;
use App\DataFixtures\Helper\FixtureHelper;
use App\DataFixtures\Helper\FixtureReference;
use App\Entity\Organization;
use App\Entity\User;
use App\Enum\ProjectStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Génère les projets de démonstration (25 projets répartis dans 4 orgs).
 *
 * Dépend de :
 * - OrganizationUserFixtures
 *
 * Références créées :
 * - project-afridil, project-skymotion
 * - project-0 à project-24
 */
final class ProjectFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['project', 'demo', 'test'];
    }

    public function getDependencies(): array
    {
        return [OrganizationUserFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $projectIndex = 0;
        $projectNames = FixtureHelper::projectNames();

        // ── Org 0 : Afridil Digital — 10 projets (croissance) ──────────
        $orgAfridil = $this->getReference(FixtureReference::ORG_AFRIDIL, Organization::class);
        $admin = $this->getReference(FixtureReference::USER_ADMIN, User::class);
        $managerUser = $this->getReference(FixtureReference::USER_MANAGER, User::class);

        $afridil = ProjectFactory::create($manager, $orgAfridil, $admin, 'Afridil — Petites annonces Afrique', ProjectStatus::ACTIVE, 'fr', 'CI');
        $afridil->addGuest($managerUser);
        $afridil->addGuest($this->getReference(FixtureReference::user(3), User::class));
        $this->addReference(FixtureReference::PROJECT_AFRIDIL, $afridil);
        $this->addReference(FixtureReference::project($projectIndex++), $afridil);

        for ($i = 0; $i < 9; $i++) {
            $name = $projectNames[$i % \count($projectNames)];
            $status = $i < 7 ? ProjectStatus::ACTIVE : ProjectStatus::PAUSED;
            $project = ProjectFactory::create($manager, $orgAfridil, $admin, $name, $status, 'fr', 'CI');
            $this->addReference(FixtureReference::project($projectIndex++), $project);
        }

        // ── Org 1 : SkyMotion Prod — 3 projets (nouveau client) ───────
        $orgSkymotion = $this->getReference(FixtureReference::ORG_SKYMOTION, Organization::class);

        $skymotion = ProjectFactory::create($manager, $orgSkymotion, $managerUser, 'SkyMotion Location', ProjectStatus::ACTIVE, 'fr', 'FR');
        $skymotion->addGuest($this->getReference(FixtureReference::user(6), User::class));
        $this->addReference(FixtureReference::PROJECT_SKYMOTION, $skymotion);
        $this->addReference(FixtureReference::project($projectIndex++), $skymotion);

        for ($i = 0; $i < 2; $i++) {
            $name = $i === 0 ? 'SkyMotion Blog' : 'SkyMotion Boutique';
            $project = ProjectFactory::create($manager, $orgSkymotion, $managerUser, $name, ProjectStatus::ACTIVE, 'fr', 'FR');
            $this->addReference(FixtureReference::project($projectIndex++), $project);
        }

        // ── Org 2 : WebPulse Agency — 10 projets (mixte) ──────────────
        $orgWebpulse = $this->getReference(FixtureReference::ORG_WEBPULSE, Organization::class);
        $webpulseOwner = $this->getReference(FixtureReference::user(8), User::class);

        for ($i = 0; $i < 10; $i++) {
            $nameIndex = (9 + $i) % \count($projectNames);
            $name = $projectNames[$nameIndex];
            $status = $i < 8 ? ProjectStatus::ACTIVE : ProjectStatus::PAUSED;
            $lang = $i < 6 ? 'fr' : 'en';
            $country = $i < 6 ? 'FR' : 'US';
            $owner = $i % 3 === 0 ? $webpulseOwner : $this->getReference(FixtureReference::user(9 + ($i % 2)), User::class);

            $project = ProjectFactory::create($manager, $orgWebpulse, $owner, $name, $status, $lang, $country);
            $this->addReference(FixtureReference::project($projectIndex++), $project);
        }

        // ── Org 3 : Studio Freelance — 2 projets (en difficulté) ──────
        $orgFreelance = $this->getReference(FixtureReference::ORG_FREELANCE, Organization::class);
        $userDemo = $this->getReference(FixtureReference::USER_USER, User::class);

        $proj1 = ProjectFactory::create($manager, $orgFreelance, $userDemo, 'Portfolio Personnel', ProjectStatus::ACTIVE, 'fr', 'FR');
        $this->addReference(FixtureReference::project($projectIndex++), $proj1);

        $proj2 = ProjectFactory::create($manager, $orgFreelance, $userDemo, 'Blog Tech Perso', ProjectStatus::PAUSED, 'fr', 'FR');
        $this->addReference(FixtureReference::project($projectIndex++), $proj2);

        $manager->flush();
    }
}
