<?php

declare(strict_types=1);

namespace App\DataFixtures\Project;

use App\DataFixtures\Helper\FixtureConfig;
use App\DataFixtures\Helper\FixtureHelper;
use App\DataFixtures\Helper\FixtureReference;
use App\Entity\Domain;
use App\Entity\Project;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Génère un domaine par projet (25 domaines).
 *
 * Dépend de :
 * - ProjectFixtures
 *
 * Références créées :
 * - domain-afridil, domain-skymotion
 * - domain-0 à domain-24
 */
final class DomainFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['project', 'demo', 'test'];
    }

    public function getDependencies(): array
    {
        return [ProjectFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $projectDomains = FixtureHelper::projectDomains();

        for ($i = 0; $i < FixtureConfig::PROJECTS; $i++) {
            $project = $this->getReference(FixtureReference::project($i), Project::class);

            $rootDomain = match ($i) {
                0 => 'afridil.com',
                10 => 'skymotionlocation.com',
                default => $projectDomains[$i % \count($projectDomains)],
            };

            $domain = new Domain();
            $domain->setRootDomain($rootDomain);
            $project->addDomain($domain);

            // Vérifier ~70% des domaines
            if (random_int(0, 100) < 70) {
                $domain->setVerifiedAt(new \DateTimeImmutable(sprintf('-%d days', random_int(10, 90))));
                $domain->setVerificationMethod(match (random_int(0, 2)) {
                    0 => 'dns',
                    1 => 'html',
                    default => 'meta',
                });
            }

            $manager->persist($domain);

            $this->addReference(FixtureReference::domain($i), $domain);

            if ($i === 0) {
                $this->addReference(FixtureReference::DOMAIN_AFRIDIL, $domain);
            } elseif ($i === 10) {
                $this->addReference(FixtureReference::DOMAIN_SKYMOTION, $domain);
            }
        }

        $manager->flush();
    }
}
