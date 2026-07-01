<?php

declare(strict_types=1);

namespace App\DataFixtures\Backlink;

use App\DataFixtures\Helper\FixtureConfig;
use App\DataFixtures\Helper\FixtureReference;
use App\DataFixtures\Project\DomainFixtures;
use App\Entity\BacklinkSite;
use App\Entity\Domain;
use App\Entity\Project;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Génère 10 sites partenaires pour les backlinks.
 *
 * Dépend de :
 * - DomainFixtures
 */
final class BacklinkSiteFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['backlink', 'demo'];
    }

    public function getDependencies(): array
    {
        return [DomainFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $niches = [
            'Marketing Digital', 'E-commerce', 'Technologie', 'Média',
            'Finance', 'Immobilier', 'Voyage', 'Santé', 'Éducation', 'Mode',
        ];

        for ($i = 0; $i < FixtureConfig::BACKLINK_SITES; ++$i) {
            $projectIdx = $i % FixtureConfig::PROJECTS;
            $project = $this->getReference(FixtureReference::project($projectIdx), Project::class);
            $domain = $this->getReference(FixtureReference::domain($projectIdx), Domain::class);

            $site = new BacklinkSite();
            $site
                ->setProject($project)
                ->setDomain($domain)
                ->setNiche($niches[$i])
                ->setDomainAuthority(random_int(15, 85))
                ->setTrafficEstimate(random_int(500, 50000))
                ->setAcceptsExchanges($i < 7); // 7 sur 10 acceptent les échanges

            $manager->persist($site);
        }

        $manager->flush();
    }
}
