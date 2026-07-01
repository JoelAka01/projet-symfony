<?php

declare(strict_types=1);

namespace App\DataFixtures\Analytics;

use App\DataFixtures\Helper\FixtureReference;
use App\DataFixtures\Project\ProjectFixtures;
use App\Entity\GeoDailySnapshot;
use App\Entity\Project;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Génère 30 snapshots GEO quotidiens.
 *
 * Dépend de :
 * - ProjectFixtures
 */
final class GeoDailySnapshotFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
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
            [FixtureReference::project(13), 'stable_good', 5],
        ];

        foreach ($projectConfigs as [$projectRef, $profile, $days]) {
            $project = $this->getReference($projectRef, Project::class);

            for ($d = 0; $d < $days; ++$d) {
                $snapshotDate = new \DateTimeImmutable(sprintf('-%d days', $days - $d));
                $progress = $d / max(1, $days - 1);

                $geoScore = match ($profile) {
                    'growing' => (int) (35 + ($progress * 40) + random_int(-3, 3)),
                    'new' => random_int(20, 35),
                    default => random_int(65, 80),
                };

                $snapshot = new GeoDailySnapshot();
                $snapshot
                    ->setProject($project)
                    ->setSnapshotDate($snapshotDate)
                    ->setGeoScore(max(0, min(100, $geoScore)))
                    ->setPromptsChecked(random_int(5, 20))
                    ->setMentionsCount(random_int(0, (int) ($geoScore / 10)))
                    ->setCitationsCount(random_int(0, (int) ($geoScore / 20)));

                $manager->persist($snapshot);
            }
        }

        $manager->flush();
    }
}
