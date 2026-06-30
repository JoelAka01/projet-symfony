<?php

declare(strict_types=1);

namespace App\DataFixtures\Geo;

use App\DataFixtures\Helper\FixtureConfig;
use App\DataFixtures\Helper\FixtureHelper;
use App\DataFixtures\Helper\FixtureReference;
use App\Entity\GeoPrompt;
use App\Entity\GeoResult;
use App\Enum\GeoProvider;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Génère 60 résultats GEO (3 par prompt, providers variés).
 *
 * Dépend de :
 * - GeoPromptFixtures
 *
 * Cohérence :
 * - Projets performants (Afridil/WebPulse) → mentionedBrand: true, visibilityScore élevé
 * - Projets en difficulté → mentionedBrand: false, visibilityScore bas
 */
final class GeoResultFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['geo', 'demo'];
    }

    public function getDependencies(): array
    {
        return [GeoPromptFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $faker = FixtureHelper::faker();
        $providers = GeoProvider::cases();

        for ($p = 0; $p < FixtureConfig::GEO_PROMPTS; $p++) {
            $geoPrompt = $this->getReference(FixtureReference::geoPrompt($p), GeoPrompt::class);

            // Déterminer le profil de visibilité
            $isPerformant = $p < 17; // Afridil (0-6) + SkyMotion (7-10) + WebPulse (11-17)
            $isAfridil = $p < 7;

            $resultCount = min(3, (int) ceil(FixtureConfig::GEO_RESULTS / FixtureConfig::GEO_PROMPTS));

            for ($r = 0; $r < $resultCount; $r++) {
                $provider = $providers[$r % \count($providers)];
                $mentionedBrand = $isPerformant && random_int(0, 100) < 70;
                $citedUrl = $isPerformant && random_int(0, 100) < 50;

                $result = new GeoResult();
                $result
                    ->setGeoPrompt($geoPrompt)
                    ->setProvider($provider)
                    ->setResponseText($faker->paragraphs(2, true))
                    ->setMentionedBrand($mentionedBrand)
                    ->setCitedProjectUrl($citedUrl)
                    ->setVisibilityScore($isPerformant ? random_int(50, 95) : random_int(5, 35));

                if ($mentionedBrand) {
                    $domain = $isAfridil ? 'afridil.com' : 'example.com';
                    $result->setCitedUrlsJson([sprintf('https://%s/', $domain)]);
                }

                if (random_int(0, 100) < 60) {
                    $result->setCompetitorsJson([
                        ['name' => $faker->company(), 'mentioned' => true],
                        ['name' => $faker->company(), 'mentioned' => random_int(0, 100) < 50],
                    ]);
                }

                $result->setSentimentScore((string) round(random_int(-100, 100) / 100, 2));

                $manager->persist($result);
            }
        }

        $manager->flush();
    }
}
