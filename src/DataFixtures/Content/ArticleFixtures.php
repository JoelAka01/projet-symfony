<?php

declare(strict_types=1);

namespace App\DataFixtures\Content;

use App\DataFixtures\Factory\ArticleFactory;
use App\DataFixtures\Helper\FixtureHelper;
use App\DataFixtures\Helper\FixtureReference;
use App\DataFixtures\Seo\KeywordFixtures;
use App\Entity\Keyword;
use App\Entity\Project;
use App\Enum\ArticleStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Génère 50 articles (héritage JOINED ContentItem → Article).
 *
 * Dépend de :
 * - KeywordFixtures (et transitif : ProjectFixtures)
 *
 * Références créées :
 * - article-0 à article-49
 *
 * Distribution :
 * - Afridil (~15) : majorité PUBLISHED (croissance)
 * - SkyMotion (~5) : DRAFT uniquement (nouveau client)
 * - WebPulse (~25) : mix de tous les statuts
 * - Studio Freelance (~5) : DRAFT/FAILED (en difficulté)
 */
final class ArticleFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['content', 'demo', 'test'];
    }

    public function getDependencies(): array
    {
        return [KeywordFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $articleIndex = 0;
        $faker = FixtureHelper::faker();

        // ── Afridil — 15 articles (majorité publiés) ───────────────────
        $afridil = $this->getReference(FixtureReference::PROJECT_AFRIDIL, Project::class);
        $afridilTitles = FixtureHelper::afridilArticleTitles();
        $afridilStatuses = [
            ArticleStatus::PUBLISHED, ArticleStatus::PUBLISHED, ArticleStatus::PUBLISHED,
            ArticleStatus::PUBLISHED, ArticleStatus::PUBLISHED, ArticleStatus::PUBLISHED,
            ArticleStatus::PUBLISHED, ArticleStatus::PUBLISHED, ArticleStatus::GENERATED,
            ArticleStatus::GENERATED, ArticleStatus::GENERATED, ArticleStatus::DRAFT,
            ArticleStatus::DRAFT, ArticleStatus::SCHEDULED, ArticleStatus::DRAFT,
        ];

        foreach ($afridilTitles as $i => $title) {
            $status = $afridilStatuses[$i] ?? ArticleStatus::DRAFT;
            $primaryKw = $i < 30 ? $this->getReference(FixtureReference::keyword($i), Keyword::class) : null;
            $article = ArticleFactory::create($manager, $afridil, $title, $status, $primaryKw, ArticleStatus::PUBLISHED === $status ? random_int(65, 85) : null, random_int(800, 2500));

            // Ajouter des target keywords ManyToMany
            if (null !== $primaryKw && $i + 1 < 30) {
                $article->addTargetKeyword($this->getReference(FixtureReference::keyword($i + 1), Keyword::class));
            }

            $this->addReference(FixtureReference::article($articleIndex++), $article);
        }

        // ── SkyMotion — 5 articles (tous DRAFT, nouveau client) ───────
        $skymotion = $this->getReference(FixtureReference::PROJECT_SKYMOTION, Project::class);
        $skymotionTitles = FixtureHelper::skymotionArticleTitles();

        foreach ($skymotionTitles as $i => $title) {
            $primaryKw = ($i + 30) < 60 ? $this->getReference(FixtureReference::keyword($i + 30), Keyword::class) : null;
            $article = ArticleFactory::create($manager, $skymotion, $title, ArticleStatus::DRAFT, $primaryKw);
            $this->addReference(FixtureReference::article($articleIndex++), $article);
        }

        // ── WebPulse — 25 articles (mix de statuts) ───────────────────
        $webpulseStatuses = [
            ArticleStatus::PUBLISHED, ArticleStatus::PUBLISHED, ArticleStatus::PUBLISHED,
            ArticleStatus::PUBLISHED, ArticleStatus::PUBLISHED, ArticleStatus::PUBLISHED,
            ArticleStatus::PUBLISHED, ArticleStatus::PUBLISHED, ArticleStatus::PUBLISHED,
            ArticleStatus::PUBLISHED, ArticleStatus::GENERATED, ArticleStatus::GENERATED,
            ArticleStatus::GENERATED, ArticleStatus::GENERATED, ArticleStatus::GENERATED,
            ArticleStatus::DRAFT, ArticleStatus::DRAFT, ArticleStatus::DRAFT,
            ArticleStatus::DRAFT, ArticleStatus::DRAFT, ArticleStatus::DRAFT,
            ArticleStatus::SCHEDULED, ArticleStatus::SCHEDULED, ArticleStatus::FAILED,
            ArticleStatus::FAILED,
        ];

        for ($i = 0; $i < 25; ++$i) {
            $projectIdx = 13 + ($i % 10);
            $project = $this->getReference(FixtureReference::project($projectIdx), Project::class);
            $status = $webpulseStatuses[$i];
            $title = sprintf('%s — %s', $faker->sentence(3), $faker->words(3, true));

            $kwIdx = 60 + ($i % 100);
            $primaryKw = $this->getReference(FixtureReference::keyword($kwIdx), Keyword::class);

            $article = ArticleFactory::create($manager, $project, $title, $status, $primaryKw, ArticleStatus::PUBLISHED === $status ? random_int(55, 90) : null, random_int(600, 3000));
            $this->addReference(FixtureReference::article($articleIndex++), $article);
        }

        // ── Studio Freelance — 5 articles (DRAFT/FAILED) ─────────────
        $freelanceStatuses = [ArticleStatus::DRAFT, ArticleStatus::DRAFT, ArticleStatus::DRAFT, ArticleStatus::FAILED, ArticleStatus::FAILED];
        for ($i = 0; $i < 5; ++$i) {
            $projectIdx = 23 + ($i % 2);
            $project = $this->getReference(FixtureReference::project($projectIdx), Project::class);
            $title = sprintf('Brouillon — %s', $faker->sentence(4));

            $kwIdx = 160 + ($i % 40);
            $primaryKw = $this->getReference(FixtureReference::keyword($kwIdx), Keyword::class);

            $article = ArticleFactory::create($manager, $project, $title, $freelanceStatuses[$i], $primaryKw);
            $this->addReference(FixtureReference::article($articleIndex++), $article);
        }

        $manager->flush();
    }
}
