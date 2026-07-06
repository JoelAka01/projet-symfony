<?php

declare(strict_types=1);

namespace App\Service\Cost;

use App\Entity\Article;
use App\Entity\ContentBrief;

final class ArticleQualityGateService
{
    private const MIN_SEO_SCORE = 70;
    private const MIN_INTERNAL_LINKS = 3;
    private const MIN_WORD_COUNT = 600;

    /** @return list<string> */
    public function failures(Article $article, ?ContentBrief $brief = null, bool $requireSeoScore = true): array
    {
        $failures = [];
        $html = (string) $article->getContentHtml();

        if ($requireSeoScore && ($article->getSeoScore() ?? 0) < self::MIN_SEO_SCORE) {
            $failures[] = 'SEO score is below the minimum threshold.';
        }

        if ($this->internalLinkCount($html) < self::MIN_INTERNAL_LINKS) {
            $failures[] = 'Article needs at least 3 internal links.';
        }

        if ($this->wordCount($html) < self::MIN_WORD_COUNT) {
            $failures[] = 'Article content is too short.';
        }

        if ('' === trim((string) $article->getSeoTitle()) || '' === trim((string) $article->getSeoDescription())) {
            $failures[] = 'SEO title and meta description are required.';
        }

        if (!$this->hasValidSlug($article->getSlug())) {
            $failures[] = 'Article slug is missing or invalid.';
        }

        if (!$this->hasValidHtml($html)) {
            $failures[] = 'Article HTML is invalid.';
        }

        if (null !== $brief && !$this->matchesIntent($article, $brief)) {
            $failures[] = 'Article does not clearly match the target intent.';
        }

        return $failures;
    }

    public function assertPasses(Article $article, ?ContentBrief $brief = null, bool $requireSeoScore = true): void
    {
        $failures = $this->failures($article, $brief, $requireSeoScore);
        if ([] !== $failures) {
            throw new \RuntimeException('Quality Gate failed: ' . implode(' ', $failures));
        }
    }

    private function internalLinkCount(string $html): int
    {
        preg_match_all('/<a\s+[^>]*href=["\'](\/(?!\/)|[^"\']*localhost)[^"\']*["\']/i', $html, $matches);

        return count($matches[0]);
    }

    private function wordCount(string $html): int
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');
        if ('' === $text) {
            return 0;
        }

        return str_word_count($text);
    }

    private function hasValidSlug(?string $slug): bool
    {
        if (null === $slug || '' === trim($slug)) {
            return false;
        }

        return 1 === preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug);
    }

    private function hasValidHtml(string $html): bool
    {
        if ('' === trim($html)) {
            return false;
        }

        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $loaded = $document->loadHTML('<!DOCTYPE html><html><body>' . $html . '</body></html>');
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded && [] === array_filter(
            $errors,
            static fn(\LibXMLError $error): bool => $error->level >= LIBXML_ERR_ERROR,
        );
    }

    private function matchesIntent(Article $article, ContentBrief $brief): bool
    {
        $intent = mb_strtolower((string) $brief->getIntent());
        if ('' === $intent) {
            return true;
        }

        $text = mb_strtolower(strip_tags((string) $article->getContentHtml()));
        if (str_contains($intent, 'transactional') || str_contains($intent, 'commercial')) {
            return 1 === preg_match('/\b(devis|contact|prix|tarif|location|service|solution|acheter|demander)\b/u', $text);
        }

        return true;
    }
}
