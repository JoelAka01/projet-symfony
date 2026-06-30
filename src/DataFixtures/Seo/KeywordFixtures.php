<?php

declare(strict_types=1);

namespace App\DataFixtures\Seo;

use App\DataFixtures\Factory\KeywordFactory;
use App\DataFixtures\Helper\FixtureHelper;
use App\DataFixtures\Helper\FixtureReference;
use App\Entity\KeywordCluster;
use App\Entity\Project;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Génère 200 mots-clés répartis sur les projets avec données réalistes.
 *
 * Dépend de :
 * - KeywordClusterFixtures
 *
 * Références créées :
 * - keyword-0 à keyword-199
 *
 * Distribution :
 * - Afridil : ~30 mots-clés (listes custom)
 * - SkyMotion : ~30 mots-clés (listes custom)
 * - WebPulse : ~100 mots-clés (SEO génériques + Faker)
 * - Studio Freelance : ~40 mots-clés (Faker)
 */
final class KeywordFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['seo', 'demo', 'test'];
    }

    public function getDependencies(): array
    {
        return [KeywordClusterFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $kwIndex = 0;
        $faker = FixtureHelper::faker();

        // ── Afridil — 30 mots-clés custom ──────────────────────────────
        $afridil = $this->getReference(FixtureReference::PROJECT_AFRIDIL, Project::class);
        $afridilClusterMap = [
            'immobilier' => $this->getReference(FixtureReference::keywordCluster(0), KeywordCluster::class),
            'automobile' => $this->getReference(FixtureReference::keywordCluster(1), KeywordCluster::class),
            'emploi' => $this->getReference(FixtureReference::keywordCluster(2), KeywordCluster::class),
            'electronique' => $this->getReference(FixtureReference::keywordCluster(3), KeywordCluster::class),
            'services' => $this->getReference(FixtureReference::keywordCluster(4), KeywordCluster::class),
        ];

        foreach (FixtureHelper::afridilKeywords() as $data) {
            $cluster = $this->matchCluster($data['term'], $afridilClusterMap);
            $kw = KeywordFactory::create($manager, $afridil, $data['term'], $data['volume'], $data['difficulty'], $data['cpc'], $data['intent'], $cluster);
            $this->addReference(FixtureReference::keyword($kwIndex++), $kw);
        }

        // ── SkyMotion — 30 mots-clés custom ────────────────────────────
        $skymotion = $this->getReference(FixtureReference::PROJECT_SKYMOTION, Project::class);
        $skymotionClusterMap = [
            'caméra' => $this->getReference(FixtureReference::keywordCluster(5), KeywordCluster::class),
            'objectif' => $this->getReference(FixtureReference::keywordCluster(6), KeywordCluster::class),
            'éclairage' => $this->getReference(FixtureReference::keywordCluster(7), KeywordCluster::class),
            'son' => $this->getReference(FixtureReference::keywordCluster(8), KeywordCluster::class),
            'machinerie' => $this->getReference(FixtureReference::keywordCluster(9), KeywordCluster::class),
        ];

        foreach (FixtureHelper::skymotionKeywords() as $data) {
            $cluster = $this->matchCluster($data['term'], $skymotionClusterMap);
            $kw = KeywordFactory::create($manager, $skymotion, $data['term'], $data['volume'], $data['difficulty'], $data['cpc'], $data['intent'], $cluster);
            $this->addReference(FixtureReference::keyword($kwIndex++), $kw);
        }

        // ── WebPulse — 100 mots-clés (20 custom SEO + 80 Faker) ──────
        $genericSeoKw = FixtureHelper::genericSeoKeywords();
        $webpulseClusterMap = [
            'seo' => $this->getReference(FixtureReference::keywordCluster(10), KeywordCluster::class),
            'contenu' => $this->getReference(FixtureReference::keywordCluster(11), KeywordCluster::class),
            'netlinking' => $this->getReference(FixtureReference::keywordCluster(12), KeywordCluster::class),
            'local' => $this->getReference(FixtureReference::keywordCluster(13), KeywordCluster::class),
            'ia' => $this->getReference(FixtureReference::keywordCluster(14), KeywordCluster::class),
        ];

        for ($i = 0; $i < 100; $i++) {
            $projectIdx = 13 + ($i % 10);
            $project = $this->getReference(FixtureReference::project($projectIdx), Project::class);

            if ($i < 20) {
                $data = $genericSeoKw[$i];
                $cluster = $this->matchCluster($data['term'], $webpulseClusterMap);
                $kw = KeywordFactory::create($manager, $project, $data['term'], $data['volume'], $data['difficulty'], $data['cpc'], $data['intent'], $cluster);
            } else {
                $term = $faker->words(random_int(2, 4), true);
                $kw = KeywordFactory::create(
                    $manager,
                    $project,
                    \is_string($term) ? $term : implode(' ', $term),
                    random_int(100, 15000),
                    random_int(10, 80),
                    (string) round(random_int(10, 1500) / 100, 2),
                    $faker->randomElement(['informational', 'transactional', 'commercial', 'navigational']),
                );
            }

            $this->addReference(FixtureReference::keyword($kwIndex++), $kw);
        }

        // ── Studio Freelance — 40 mots-clés Faker ─────────────────────
        for ($i = 0; $i < 40; $i++) {
            $projectIdx = 23 + ($i % 2);
            $project = $this->getReference(FixtureReference::project($projectIdx), Project::class);

            $term = $faker->words(random_int(2, 4), true);
            $kw = KeywordFactory::create(
                $manager,
                $project,
                \is_string($term) ? $term : implode(' ', $term),
                random_int(50, 5000),
                random_int(15, 70),
                (string) round(random_int(5, 800) / 100, 2),
                $faker->randomElement(['informational', 'transactional', 'commercial']),
            );

            $this->addReference(FixtureReference::keyword($kwIndex++), $kw);
        }

        $manager->flush();
    }

    /**
     * @param array<string, KeywordCluster> $clusterMap
     */
    private function matchCluster(string $term, array $clusterMap): KeywordCluster
    {
        $termLower = mb_strtolower($term);

        foreach ($clusterMap as $keyword => $cluster) {
            if (str_contains($termLower, $keyword)) {
                return $cluster;
            }
        }

        // Retour aléatoire si aucun match
        $clusters = array_values($clusterMap);

        return $clusters[array_rand($clusters)];
    }
}
