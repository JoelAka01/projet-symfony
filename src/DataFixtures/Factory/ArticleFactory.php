<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\Article;
use App\Entity\Keyword;
use App\Entity\Project;
use App\Enum\ArticleStatus;
use Doctrine\Persistence\ObjectManager;

final class ArticleFactory
{
    public static function create(
        ObjectManager $manager,
        Project $project,
        string $title,
        ArticleStatus $status = ArticleStatus::DRAFT,
        ?Keyword $primaryKeyword = null,
        ?int $seoScore = null,
        ?int $wordCount = null,
        ?string $contentHtml = null,
    ): Article {
        $article = new Article();
        $article
            ->setProject($project)
            ->setTitle($title)
            ->setStatus($status)
            ->setPrimaryKeyword($primaryKeyword)
            ->setSeoScore($seoScore)
            ->setWordCount($wordCount);

        if (ArticleStatus::PUBLISHED === $status || ArticleStatus::GENERATED === $status) {
            $slug = self::slugify($title);
            $article
                ->setSlug($slug)
                ->setSeoTitle(mb_substr($title, 0, 70))
                ->setSeoDescription(sprintf('Découvrez notre article : %s. Conseils et informations détaillées.', mb_substr($title, 0, 120)))
                ->setContentHtml($contentHtml ?? self::generatePlaceholderHtml($title))
                ->setContentMarkdown(strip_tags($contentHtml ?? $title))
                ->setWordCount($wordCount ?? random_int(800, 2500))
                ->setGeoScore(random_int(40, 85))
                ->setGeneratedByProvider('anthropic')
                ->setGeneratedAt(new \DateTimeImmutable(sprintf('-%d days', random_int(1, 60))));
        }

        if (ArticleStatus::PUBLISHED === $status) {
            $article->setPublishedAt(new \DateTimeImmutable(sprintf('-%d days', random_int(1, 30))));
        }

        if (ArticleStatus::SCHEDULED === $status) {
            $article->setScheduledAt(new \DateTimeImmutable(sprintf('+%d days', random_int(1, 14))));
        }

        $manager->persist($article);

        return $article;
    }

    private static function slugify(string $text): string
    {
        $text = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text) ?: mb_strtolower($text);
        $text = (string) preg_replace('/[^a-z0-9]+/', '-', $text);

        return trim($text, '-');
    }

    private static function generatePlaceholderHtml(string $title): string
    {
        return sprintf(
            '<h1>%s</h1><p>Cet article présente une analyse approfondie du sujet. '
            . 'Nos experts ont compilé les meilleures pratiques et recommandations.</p>'
            . '<h2>Points clés</h2><ul><li>Analyse détaillée du marché</li>'
            . '<li>Recommandations stratégiques</li><li>Tendances actuelles</li></ul>'
            . '<h2>Conclusion</h2><p>En résumé, ce sujet mérite une attention particulière '
            . 'pour optimiser votre stratégie digitale.</p>',
            htmlspecialchars($title),
        );
    }
}
