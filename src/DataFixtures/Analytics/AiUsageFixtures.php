<?php

declare(strict_types=1);

namespace App\DataFixtures\Analytics;

use App\DataFixtures\Core\UserFixtures;
use App\DataFixtures\Helper\FixtureConfig;
use App\DataFixtures\Helper\FixtureReference;
use App\DataFixtures\Project\ProjectFixtures;
use App\Entity\AiUsage;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Génère 20 entrées d'usage AI.
 *
 * Dépend de :
 * - UserFixtures
 * - ProjectFixtures
 */
final class AiUsageFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['analytics', 'demo'];
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            ProjectFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $operations = ['seo_analysis', 'article_generation', 'keyword_research', 'content_optimization', 'geo_analysis'];
        $models = ['claude-haiku-4-5-20251001', 'claude-sonnet-4-20250514'];

        for ($i = 0; $i < FixtureConfig::AI_USAGES; ++$i) {
            $userIdx = $i % FixtureConfig::USERS;
            $projectIdx = $i % FixtureConfig::PROJECTS;

            $user = $this->getReference(FixtureReference::user($userIdx), User::class);
            $project = $this->getReference(FixtureReference::project($projectIdx), Project::class);

            $inputTokens = random_int(500, 5000);
            $outputTokens = random_int(200, 3000);

            $usage = new AiUsage();
            $usage
                ->setUser($user)
                ->setProject($project)
                ->setProvider('anthropic')
                ->setModel($models[array_rand($models)])
                ->setOperation($operations[array_rand($operations)])
                ->setInputTokens($inputTokens)
                ->setOutputTokens($outputTokens)
                ->setCachedInputTokens((int) ($inputTokens * 0.3))
                ->setCredits($inputTokens + $outputTokens * 3)
                ->setCreatedAt(new \DateTimeImmutable(sprintf('-%d days -%d hours', random_int(0, 30), random_int(0, 23))));

            $manager->persist($usage);
        }

        $manager->flush();
    }
}
