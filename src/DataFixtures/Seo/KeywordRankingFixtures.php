<?php

declare(strict_types=1);

namespace App\DataFixtures\Seo;

use App\DataFixtures\Helper\FixtureConfig;
use App\DataFixtures\Helper\FixtureHelper;
use App\DataFixtures\Helper\FixtureReference;
use App\Entity\Keyword;
use App\Entity\KeywordRanking;
use App\Entity\Project;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Génère ~1000 historiques de positionnement avec tendances cohérentes.
 *
 * Dépend de :
 * - KeywordFixtures
 *
 * Tendances par profil :
 * - growing   : position s'améliore (45→28→15)
 * - stable_good : fluctuations légères en top 15
 * - struggling : position se dégrade (15→35→60)
 * - new       : position stable haute (30-60)
 */
final class KeywordRankingFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    private const RANKINGS_PER_KEYWORD = 5;

    public static function getGroups(): array
    {
        return ['seo', 'demo'];
    }

    public function getDependencies(): array
    {
        return [KeywordFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $rankingIndex = 0;

        for ($k = 0; $k < FixtureConfig::KEYWORDS; $k++) {
            $keyword = $this->getReference(FixtureReference::keyword($k), Keyword::class);
            $project = $keyword->getProject();

            if ($project === null) {
                continue;
            }

            $profile = $this->profileForKeywordIndex($k);
            $country = $project->getTargetCountry() ?? 'FR';

            $previousPosition = null;

            for ($w = 0; $w < self::RANKINGS_PER_KEYWORD; $w++) {
                $checkedAt = new \DateTimeImmutable(sprintf('-%d days', (self::RANKINGS_PER_KEYWORD - 1 - $w) * 7));
                $position = FixtureHelper::rankPositionForProfile($profile, $w);
                $position = max(1, min(100, $position));

                $ranking = new KeywordRanking();
                $ranking
                    ->setKeyword($keyword)
                    ->setProject($project)
                    ->setRankPosition($position)
                    ->setPreviousRankPosition($previousPosition)
                    ->setSearchEngine($w % 5 === 0 ? 'bing' : 'google')
                    ->setDevice($w % 3 === 0 ? 'mobile' : 'desktop')
                    ->setCountry($country)
                    ->setCheckedAt($checkedAt);

                $manager->persist($ranking);
                $previousPosition = $position;
                $rankingIndex++;

                FixtureHelper::batchFlush($manager, $rankingIndex);
            }
        }

        $manager->flush();
    }

    private function profileForKeywordIndex(int $index): string
    {
        // 0-29: Afridil (growing), 30-59: SkyMotion (new), 60-159: WebPulse (stable_good), 160-199: Freelance (struggling)
        if ($index < 30) {
            return 'growing';
        }
        if ($index < 60) {
            return 'new';
        }
        if ($index < 160) {
            return 'stable_good';
        }

        return 'struggling';
    }
}
