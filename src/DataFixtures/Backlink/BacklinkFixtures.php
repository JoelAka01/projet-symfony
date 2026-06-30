<?php

declare(strict_types=1);

namespace App\DataFixtures\Backlink;

use App\DataFixtures\Helper\FixtureConfig;
use App\DataFixtures\Helper\FixtureHelper;
use App\DataFixtures\Helper\FixtureReference;
use App\DataFixtures\Project\DomainFixtures;
use App\Entity\Backlink;
use App\Entity\Project;
use App\Enum\BacklinkStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Génère 30 backlinks entre projets.
 *
 * Dépend de :
 * - DomainFixtures
 *
 * Références créées :
 * - backlink-0 à backlink-29
 *
 * Cohérence scénario :
 * - Afridil/WebPulse : PLACED, ACCEPTED (projets performants)
 * - Studio Freelance : BROKEN, REMOVED (en difficulté)
 */
final class BacklinkFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
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
        $faker = FixtureHelper::faker();
        $projectDomains = FixtureHelper::projectDomains();

        $statusDistribution = [
            BacklinkStatus::PLACED, BacklinkStatus::PLACED, BacklinkStatus::PLACED,
            BacklinkStatus::PLACED, BacklinkStatus::PLACED, BacklinkStatus::PLACED,
            BacklinkStatus::PLACED, BacklinkStatus::PLACED, BacklinkStatus::PLACED,
            BacklinkStatus::ACCEPTED, BacklinkStatus::ACCEPTED, BacklinkStatus::ACCEPTED,
            BacklinkStatus::ACCEPTED, BacklinkStatus::ACCEPTED,
            BacklinkStatus::PROPOSED, BacklinkStatus::PROPOSED, BacklinkStatus::PROPOSED,
            BacklinkStatus::PROPOSED, BacklinkStatus::PROPOSED, BacklinkStatus::PROPOSED,
            BacklinkStatus::PROPOSED, BacklinkStatus::PROPOSED,
            BacklinkStatus::BROKEN, BacklinkStatus::BROKEN, BacklinkStatus::BROKEN,
            BacklinkStatus::REMOVED, BacklinkStatus::REMOVED,
            BacklinkStatus::REJECTED, BacklinkStatus::REJECTED, BacklinkStatus::REJECTED,
        ];

        $anchorTexts = [
            'Cliquez ici', 'En savoir plus', 'Voir le site', 'Découvrir',
            'Site officiel', 'Lire l\'article', 'Partenaire', 'Recommandé par',
            'Source', 'Référence', 'Guide complet', 'Comparatif',
        ];

        for ($i = 0; $i < FixtureConfig::BACKLINKS; $i++) {
            // Choisir source et target (projets différents)
            $sourceIdx = $i % FixtureConfig::PROJECTS;
            $targetIdx = ($sourceIdx + random_int(1, FixtureConfig::PROJECTS - 1)) % FixtureConfig::PROJECTS;

            $sourceProject = $this->getReference(FixtureReference::project($sourceIdx), Project::class);
            $targetProject = $this->getReference(FixtureReference::project($targetIdx), Project::class);

            // Forcer BROKEN/REMOVED pour Studio Freelance (projets 23-24)
            $status = $statusDistribution[$i];
            if ($sourceIdx >= 23 || $targetIdx >= 23) {
                $status = $faker->randomElement([BacklinkStatus::BROKEN, BacklinkStatus::REMOVED]);
            }

            $sourceDomain = $projectDomains[$sourceIdx % \count($projectDomains)];
            $targetDomain = $projectDomains[$targetIdx % \count($projectDomains)];

            $backlink = new Backlink();
            $backlink
                ->setSourceProject($sourceProject)
                ->setTargetProject($targetProject)
                ->setSourceUrl(sprintf('https://%s/blog/article-%d', $sourceDomain, random_int(1, 50)))
                ->setTargetUrl(sprintf('https://%s/', $targetDomain))
                ->setAnchorText($anchorTexts[array_rand($anchorTexts)])
                ->setContextText($faker->sentence(12))
                ->setQualityScore(random_int(20, 95))
                ->setStatus($status)
                ->setFirstDetectedAt(new \DateTimeImmutable(sprintf('-%d days', random_int(10, 180))));

            if (\in_array($status, [BacklinkStatus::PLACED, BacklinkStatus::ACCEPTED], true)) {
                $backlink->setLastCheckedAt(new \DateTimeImmutable(sprintf('-%d days', random_int(0, 7))));
            }

            if ($status === BacklinkStatus::REMOVED) {
                $backlink->setRemovedAt(new \DateTimeImmutable(sprintf('-%d days', random_int(1, 30))));
            }

            $manager->persist($backlink);
            $this->addReference(FixtureReference::backlink($i), $backlink);
        }

        $manager->flush();
    }
}
