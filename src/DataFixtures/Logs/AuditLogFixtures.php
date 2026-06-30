<?php

declare(strict_types=1);

namespace App\DataFixtures\Logs;

use App\DataFixtures\Core\OrganizationFixtures;
use App\DataFixtures\Helper\FixtureConfig;
use App\DataFixtures\Helper\FixtureHelper;
use App\DataFixtures\Helper\FixtureReference;
use App\DataFixtures\Project\ProjectFixtures;
use App\Entity\AuditLog;
use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Génère 100 entrées d'audit log réparties sur 30 jours.
 *
 * Dépend de :
 * - OrganizationFixtures
 * - ProjectFixtures (transitif : UserFixtures)
 */
final class AuditLogFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['logs', 'demo'];
    }

    public function getDependencies(): array
    {
        return [
            OrganizationFixtures::class,
            ProjectFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $faker = FixtureHelper::faker();
        $actions = FixtureHelper::auditLogActions();
        $orgRefs = [FixtureReference::ORG_AFRIDIL, FixtureReference::ORG_SKYMOTION, FixtureReference::ORG_WEBPULSE, FixtureReference::ORG_FREELANCE];

        for ($i = 0; $i < FixtureConfig::AUDIT_LOG_ENTRIES; $i++) {
            $action = $actions[array_rand($actions)];
            $orgRef = $orgRefs[$i % \count($orgRefs)];
            $userIdx = $i % FixtureConfig::USERS;
            $projectIdx = $i % FixtureConfig::PROJECTS;

            $org = $this->getReference($orgRef, Organization::class);
            $user = $this->getReference(FixtureReference::user($userIdx), User::class);
            $project = $this->getReference(FixtureReference::project($projectIdx), Project::class);

            $log = new AuditLog();
            $log
                ->setOrganization($org)
                ->setUser($user)
                ->setProject($project)
                ->setAction($action['action'])
                ->setEntityType($action['entityType'])
                ->setEntityId($faker->uuid())
                ->setIpAddress($faker->localIpv4())
                ->setUserAgent($faker->userAgent())
                ->setMetadata(['fixture' => true, 'index' => $i])
                ->setCreatedAt(new \DateTimeImmutable(sprintf('-%d days -%d hours -%d minutes', random_int(0, 30), random_int(0, 23), random_int(0, 59))));

            $manager->persist($log);

            if (($i + 1) % 50 === 0) {
                $manager->flush();
            }
        }

        $manager->flush();
    }
}
