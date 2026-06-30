<?php

declare(strict_types=1);

namespace App\DataFixtures\Analytics;

use App\DataFixtures\Helper\FixtureConfig;
use App\DataFixtures\Helper\FixtureHelper;
use App\DataFixtures\Helper\FixtureReference;
use App\DataFixtures\Project\ProjectFixtures;
use App\Entity\AnalyticsDailySnapshot;
use App\Entity\Project;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Génère 30 snapshots analytics quotidiens pour les projets phares.
 *
 * Dépend de :
 * - ProjectFixtures
 *
 * Progression cohérente avec les scénarios :
 * - Afridil : SEO/GEO scores croissants, trafic en hausse
 * - SkyMotion : scores stables bas (nouveau)
 * - Freelance : scores déclinants
 */
final class AnalyticsDailySnapshotFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['analytics', 'demo'];
    }

    public function getDependencies(): array
    {
        return [ProjectFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $projectConfigs = [
            [FixtureReference::PROJECT_AFRIDIL, 'growing', 15],
            [FixtureReference::PROJECT_SKYMOTION, 'new', 10],
            [FixtureReference::project(23), 'struggling', 5],
        ];

        foreach ($projectConfigs as [$projectRef, $profile, $days]) {
            $project = $this->getReference($projectRef, Project::class);

            for ($d = 0; $d < $days; $d++) {
                $snapshotDate = new \DateTimeImmutable(sprintf('-%d days', $days - $d));
                $progress = $d / max(1, $days - 1); // 0.0 → 1.0

                $seoScore = match ($profile) {
                    'growing' => (int) (42 + ($progress * 35) + random_int(-2, 2)),
                    'new' => random_int(42, 52),
                    default => (int) (65 - ($progress * 25) + random_int(-2, 2)),
                };

                $snapshot = new AnalyticsDailySnapshot();
                $snapshot
                    ->setProject($project)
                    ->setSnapshotDate($snapshotDate)
                    ->setSeoScore(max(0, min(100, $seoScore)))
                    ->setGeoScore(max(0, min(100, $seoScore + random_int(-10, 10))))
                    ->setOrganicTraffic((int) ($seoScore * random_int(10, 50)))
                    ->setBacklinksCount(random_int(2, 30))
                    ->setPublishedArticlesCount(random_int(0, 15))
                    ->setEstimatedRoi((string) round($seoScore * random_int(5, 20) / 10, 2));

                $manager->persist($snapshot);
            }
        }

        $manager->flush();
    }
}
