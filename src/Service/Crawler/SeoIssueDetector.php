<?php

declare(strict_types=1);

namespace App\Service\Crawler;

use App\Entity\Audit;
use App\Entity\AuditIssue;
use App\Entity\AuditPage;

final class SeoIssueDetector
{
    public function __construct(private readonly CrawlerUrlNormalizer $urlNormalizer) {}

    /**
     * @param array<string, true> $seenTitles
     * @param array<string, true> $seenMetaDescriptions
     *
     * @return list<AuditIssue>
     */
    public function detect(
        ?HtmlSeoExtractionResult $result,
        ?int $statusCode,
        ?int $loadTimeMs,
        Audit $audit,
        AuditPage $page,
        string $startHostname,
        array &$seenTitles,
        array &$seenMetaDescriptions,
    ): array {
        $issues = [];

        if (null !== $statusCode && $statusCode >= 400 && $statusCode < 500) {
            $issues[] = $this->createIssue(
                $audit,
                $page,
                'http_4xx',
                'high',
                sprintf('The page returned HTTP %d.', $statusCode),
                'Fix the internal link, restore the missing page, or redirect it to a relevant live URL.',
            );
        }

        if (null !== $statusCode && $statusCode >= 500) {
            $issues[] = $this->createIssue(
                $audit,
                $page,
                'http_5xx',
                'critical',
                sprintf('The page returned HTTP %d.', $statusCode),
                'Investigate the server error and make sure the page returns a stable 2xx response.',
            );
        }

        if (null !== $loadTimeMs && $loadTimeMs > 3000) {
            $issues[] = $this->createIssue(
                $audit,
                $page,
                'slow_page',
                'medium',
                sprintf('The page took %d ms to respond, which is above the 3 second threshold.', $loadTimeMs),
                'Reduce server response time, optimize assets, and review caching for this page.',
            );
        }

        if (null === $result) {
            return $issues;
        }

        $title = $result->title;
        if (null === $title) {
            $issues[] = $this->createIssue(
                $audit,
                $page,
                'missing_title',
                'high',
                'The page does not define a title tag.',
                'Add a unique, descriptive title between 30 and 70 characters.',
            );
        } else {
            $titleLength = strlen($title);
            if ($titleLength < 30) {
                $issues[] = $this->createIssue(
                    $audit,
                    $page,
                    'title_too_short',
                    'low',
                    sprintf('The title is %d characters long, below the 30 character minimum.', $titleLength),
                    'Expand the title so it clearly describes the page topic and search intent.',
                );
            }

            if ($titleLength > 70) {
                $issues[] = $this->createIssue(
                    $audit,
                    $page,
                    'title_too_long',
                    'low',
                    sprintf('The title is %d characters long, above the 70 character maximum.', $titleLength),
                    'Shorten the title to avoid truncation in search results.',
                );
            }

            $titleKey = $this->fingerprint($title);
            if (isset($seenTitles[$titleKey])) {
                $issues[] = $this->createIssue(
                    $audit,
                    $page,
                    'duplicate_title',
                    'medium',
                    'Another crawled page already uses the same title.',
                    'Write a unique title that describes this specific page.',
                );
            }
            $seenTitles[$titleKey] = true;
        }

        $metaDescription = $result->metaDescription;
        if (null === $metaDescription) {
            $issues[] = $this->createIssue(
                $audit,
                $page,
                'missing_meta_description',
                'medium',
                'The page does not define a meta description.',
                'Add a concise 70 to 160 character meta description that can work as a search snippet.',
            );
        } else {
            $metaDescriptionLength = strlen($metaDescription);
            if ($metaDescriptionLength < 70) {
                $issues[] = $this->createIssue(
                    $audit,
                    $page,
                    'meta_description_too_short',
                    'low',
                    sprintf('The meta description is %d characters long, below the 70 character minimum.', $metaDescriptionLength),
                    'Expand the meta description with a clearer page summary and value proposition.',
                );
            }

            if ($metaDescriptionLength > 160) {
                $issues[] = $this->createIssue(
                    $audit,
                    $page,
                    'meta_description_too_long',
                    'low',
                    sprintf('The meta description is %d characters long, above the 160 character maximum.', $metaDescriptionLength),
                    'Shorten the meta description so important information is not truncated.',
                );
            }

            $metaDescriptionKey = $this->fingerprint($metaDescription);
            if (isset($seenMetaDescriptions[$metaDescriptionKey])) {
                $issues[] = $this->createIssue(
                    $audit,
                    $page,
                    'duplicate_meta_description',
                    'medium',
                    'Another crawled page already uses the same meta description.',
                    'Write a unique meta description that reflects this page content.',
                );
            }
            $seenMetaDescriptions[$metaDescriptionKey] = true;
        }

        $h1Count = count($result->h1Headings);
        if (0 === $h1Count) {
            $issues[] = $this->createIssue(
                $audit,
                $page,
                'missing_h1',
                'high',
                'The page does not contain an H1 heading.',
                'Add one clear H1 heading that states the main topic of the page.',
            );
        }

        if ($h1Count > 1) {
            $issues[] = $this->createIssue(
                $audit,
                $page,
                'multiple_h1',
                'medium',
                sprintf('The page contains %d H1 headings.', $h1Count),
                'Keep one primary H1 and demote secondary headings to H2 or lower.',
            );
        }

        if ($result->imagesWithoutAltCount > 0) {
            $issues[] = $this->createIssue(
                $audit,
                $page,
                'images_missing_alt',
                'low',
                sprintf('%d image(s) are missing alt text.', $result->imagesWithoutAltCount),
                'Add useful alt text to informative images and empty alt text only for decorative images.',
            );
        }

        if (null === $result->canonicalUrl) {
            $issues[] = $this->createIssue(
                $audit,
                $page,
                'missing_canonical',
                'low',
                'The page does not define a canonical URL.',
                'Add a canonical link pointing to the preferred indexable URL for this page.',
            );
        } elseif (!$this->urlNormalizer->isSameHostname($result->canonicalUrl, $startHostname)) {
            $issues[] = $this->createIssue(
                $audit,
                $page,
                'external_canonical',
                'high',
                'The page canonical URL points to a different hostname.',
                'Use a canonical URL on the audited hostname unless cross-domain canonicalization is intentional.',
            );
        }

        return $issues;
    }

    public function createFetchErrorIssue(Audit $audit, AuditPage $page, string $errorMessage): AuditIssue
    {
        return $this->createIssue(
            $audit,
            $page,
            'fetch_error',
            'high',
            sprintf('The crawler could not fetch this page: %s', $errorMessage),
            'Check that the URL is reachable and does not block normal HTTP clients.',
        );
    }

    private function createIssue(
        Audit $audit,
        AuditPage $page,
        string $type,
        string $severity,
        string $message,
        string $recommendation,
    ): AuditIssue {
        $issue = new AuditIssue();
        $issue
            ->setIssueType($type)
            ->setSeverity($severity)
            ->setMessage($message)
            ->setRecommendation($recommendation);

        $audit->addIssue($issue);
        $page->addIssue($issue);

        return $issue;
    }

    private function fingerprint(string $value): string
    {
        return strtolower(trim((string) preg_replace('/\s+/', ' ', $value)));
    }
}
