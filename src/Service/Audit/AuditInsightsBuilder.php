<?php

declare(strict_types=1);

namespace App\Service\Audit;

use App\Entity\Audit;
use App\Entity\AuditIssue;
use App\Entity\AuditPage;

final class AuditInsightsBuilder
{
    private const SEVERITIES = ['critical', 'high', 'medium', 'low', 'info'];

    private const CATEGORIES = [
        'metadata',
        'headings',
        'links',
        'images',
        'indexability',
        'performance',
        'structured_data',
        'content',
        'crawl',
    ];

    /** @return array<string, mixed> */
    public function build(Audit $audit): array
    {
        /** @var list<AuditPage> $pages */
        $pages = array_values($audit->getPages()->toArray());
        /** @var list<AuditIssue> $issues */
        $issues = array_values($audit->getIssues()->toArray());

        $severityCounts = array_fill_keys(self::SEVERITIES, 0);
        $categoryCounts = array_fill_keys(self::CATEGORIES, 0);
        $pageIssueCounts = [];

        foreach ($issues as $issue) {
            $severity = strtolower((string) $issue->getSeverity());
            if (!array_key_exists($severity, $severityCounts)) {
                $severity = 'medium';
            }
            ++$severityCounts[$severity];

            $category = $this->categoryForIssue($issue->getIssueType());
            ++$categoryCounts[$category];

            $page = $issue->getAuditPage();
            if ($page instanceof AuditPage) {
                $pageIssueCounts[spl_object_id($page)] = ($pageIssueCounts[spl_object_id($page)] ?? 0) + 1;
            }
        }

        $pageStats = $this->pageStats($pages);
        $topPages = $this->topPages($pages, $pageIssueCounts);
        $metadata = $audit->getMetadata() ?? [];
        $aiAnalysis = $this->normalizeAiAnalysis($metadata['ai_analysis'] ?? null);

        return [
            'ai' => $aiAnalysis,
            'severity_counts' => $severityCounts,
            'severity_chart' => $this->chartRows($severityCounts),
            'category_counts' => $categoryCounts,
            'category_chart' => $this->chartRows($categoryCounts),
            'page_stats' => $pageStats,
            'quality_profile' => $this->qualityProfile($audit, $pageStats),
            'link_distribution' => $this->linkDistribution($pageStats, $severityCounts),
            'top_pages' => $topPages,
            'critical_count' => $severityCounts['critical'],
            'high_count' => $severityCounts['high'],
            'open_issue_count' => count($issues),
            'score_label' => $this->scoreLabel($audit->getSeoScore()),
        ];
    }

    /** @return array<string, mixed> */
    public function buildClaudePayload(Audit $audit): array
    {
        $insights = $this->build($audit);

        /** @var list<AuditPage> $pages */
        $pages = array_values($audit->getPages()->toArray());
        /** @var list<AuditIssue> $issues */
        $issues = array_values($audit->getIssues()->toArray());

        return [
            'domain' => $audit->getDomain()?->getRootDomain(),
            'project' => [
                'name' => $audit->getProject()?->getName(),
                'default_language' => $audit->getProject()?->getDefaultLanguage(),
                'target_country' => $audit->getProject()?->getTargetCountry(),
            ],
            'crawl' => [
                'status' => $audit->getStatus()->value,
                'seo_score' => $audit->getSeoScore(),
                'pages_crawled' => $audit->getPagesCrawled(),
                'pages_failed' => $audit->getPagesFailed(),
                'max_pages' => $audit->getMaxPages(),
                'max_depth' => $audit->getMaxDepth(),
                'started_at' => $audit->getCrawlStartedAt()?->format(\DateTimeInterface::ATOM),
                'finished_at' => $audit->getCrawlFinishedAt()?->format(\DateTimeInterface::ATOM),
            ],
            'issue_counts' => [
                'by_severity' => $insights['severity_counts'],
                'by_category' => $insights['category_counts'],
            ],
            'crawler_quality_profile' => $insights['quality_profile'],
            'aggregate_page_metrics' => $insights['page_stats'],
            'top_pages_by_issue_count' => array_slice($insights['top_pages'], 0, 8),
            'sample_pages' => array_map(
                fn (AuditPage $page): array => $this->pagePayload($page),
                array_slice($pages, 0, 15),
            ),
            'top_issues' => array_map(
                fn (AuditIssue $issue): array => $this->issuePayload($issue),
                array_slice($issues, 0, 30),
            ),
            'instructional_boundary' => 'Use only these crawler facts for objective claims. Do not invent HTTP status, headings, metadata, links, page speed, indexability, or issue counts.',
        ];
    }

    /** @param list<AuditPage> $pages */
    private function pageStats(array $pages): array
    {
        $pageCount = count($pages);
        $totalInternalLinks = 0;
        $totalExternalLinks = 0;
        $totalMissingAlt = 0;
        $totalLoadTime = 0;
        $loadTimeCount = 0;
        $totalWords = 0;
        $wordCountCount = 0;
        $indexableCount = 0;
        $structuredDataCount = 0;
        $titleCount = 0;
        $metaDescriptionCount = 0;
        $h1Count = 0;
        $successfulCount = 0;

        foreach ($pages as $page) {
            $totalInternalLinks += $page->getInternalLinksCount() ?? 0;
            $totalExternalLinks += $page->getExternalLinksCount() ?? 0;
            $totalMissingAlt += $page->getImagesWithoutAltCount() ?? 0;

            if (null !== $page->getLoadTimeMs()) {
                $totalLoadTime += $page->getLoadTimeMs();
                ++$loadTimeCount;
            }

            if (null !== $page->getWordCount()) {
                $totalWords += $page->getWordCount();
                ++$wordCountCount;
            }

            if ($page->isIndexable()) {
                ++$indexableCount;
            }

            if ($page->hasStructuredData()) {
                ++$structuredDataCount;
            }

            if (null !== $page->getTitle()) {
                ++$titleCount;
            }

            if (null !== $page->getMetaDescription()) {
                ++$metaDescriptionCount;
            }

            if (null !== $page->getH1()) {
                ++$h1Count;
            }

            $statusCode = $page->getStatusCode();
            if (null !== $statusCode && $statusCode >= 200 && $statusCode < 400 && null === $page->getErrorMessage()) {
                ++$successfulCount;
            }
        }

        return [
            'page_count' => $pageCount,
            'successful_count' => $successfulCount,
            'indexable_count' => $indexableCount,
            'structured_data_count' => $structuredDataCount,
            'title_count' => $titleCount,
            'meta_description_count' => $metaDescriptionCount,
            'h1_count' => $h1Count,
            'total_internal_links' => $totalInternalLinks,
            'total_external_links' => $totalExternalLinks,
            'total_missing_alt' => $totalMissingAlt,
            'average_load_time_ms' => $loadTimeCount > 0 ? (int) round($totalLoadTime / $loadTimeCount) : null,
            'average_word_count' => $wordCountCount > 0 ? (int) round($totalWords / $wordCountCount) : null,
            'metadata_coverage' => $this->percent($titleCount + $metaDescriptionCount, max(1, $pageCount * 2)),
            'heading_coverage' => $this->percent($h1Count, max(1, $pageCount)),
            'indexability_rate' => $this->percent($indexableCount, max(1, $pageCount)),
            'structured_data_rate' => $this->percent($structuredDataCount, max(1, $pageCount)),
            'success_rate' => $this->percent($successfulCount, max(1, $pageCount)),
        ];
    }

    /**
     * @param list<AuditPage> $pages
     * @param array<int, int> $pageIssueCounts
     */
    private function topPages(array $pages, array $pageIssueCounts): array
    {
        usort($pages, static function (AuditPage $left, AuditPage $right) use ($pageIssueCounts): int {
            $leftIssues = $pageIssueCounts[spl_object_id($left)] ?? 0;
            $rightIssues = $pageIssueCounts[spl_object_id($right)] ?? 0;

            if ($leftIssues === $rightIssues) {
                return ($right->getLoadTimeMs() ?? 0) <=> ($left->getLoadTimeMs() ?? 0);
            }

            return $rightIssues <=> $leftIssues;
        });

        return array_map(
            fn (AuditPage $page): array => $this->pagePayload($page) + [
                'issue_count' => $pageIssueCounts[spl_object_id($page)] ?? 0,
            ],
            array_slice($pages, 0, 10),
        );
    }

    /** @param array<string, int> $counts */
    private function chartRows(array $counts): array
    {
        $max = max(1, ...array_values($counts));
        $rows = [];

        foreach ($counts as $label => $count) {
            $rows[] = [
                'label' => $label,
                'count' => $count,
                'percent' => $this->percent($count, $max),
            ];
        }

        return $rows;
    }

    /** @param array<string, mixed> $pageStats */
    private function qualityProfile(Audit $audit, array $pageStats): array
    {
        $performanceScore = null === $pageStats['average_load_time_ms']
            ? null
            : max(0, min(100, 100 - (int) floor(max(0, $pageStats['average_load_time_ms'] - 800) / 35)));

        $imageScore = 100;
        if (($pageStats['total_missing_alt'] ?? 0) > 0 && ($pageStats['page_count'] ?? 0) > 0) {
            $imageScore = max(0, 100 - min(100, ((int) $pageStats['total_missing_alt']) * 8));
        }

        return [
            ['label' => 'Technical SEO', 'value' => $audit->getSeoScore() ?? 0],
            ['label' => 'Metadata', 'value' => $pageStats['metadata_coverage']],
            ['label' => 'Headings', 'value' => $pageStats['heading_coverage']],
            ['label' => 'Indexability', 'value' => $pageStats['indexability_rate']],
            ['label' => 'Performance', 'value' => $performanceScore ?? 0],
            ['label' => 'Image accessibility', 'value' => $imageScore],
            ['label' => 'Structured data', 'value' => $pageStats['structured_data_rate']],
        ];
    }

    /** @param array<string, mixed> $pageStats */
    private function linkDistribution(array $pageStats, array $severityCounts): array
    {
        $internal = (int) $pageStats['total_internal_links'];
        $external = (int) $pageStats['total_external_links'];
        $broken = (int) $severityCounts['critical'] + (int) $severityCounts['high'];
        $max = max(1, $internal, $external, $broken);

        return [
            ['label' => 'Internal links', 'count' => $internal, 'percent' => $this->percent($internal, $max)],
            ['label' => 'External links', 'count' => $external, 'percent' => $this->percent($external, $max)],
            ['label' => 'Critical/high issue links', 'count' => $broken, 'percent' => $this->percent($broken, $max)],
        ];
    }

    /** @return array<string, mixed> */
    private function normalizeAiAnalysis(mixed $value): array
    {
        if (!is_array($value)) {
            return [
                'status' => 'not_started',
                'provider' => 'anthropic',
                'model' => null,
                'summary' => null,
                'recommendations' => [],
            ];
        }

        $value['status'] = is_scalar($value['status'] ?? null) ? (string) $value['status'] : 'not_started';
        $value['recommendations'] = is_array($value['recommendations'] ?? null) ? $value['recommendations'] : [];
        $value['strengths'] = is_array($value['strengths'] ?? null) ? $value['strengths'] : [];
        $value['weaknesses'] = is_array($value['weaknesses'] ?? null) ? $value['weaknesses'] : [];
        $value['faq_suggestions'] = is_array($value['faq_suggestions'] ?? null) ? $value['faq_suggestions'] : [];
        $value['short_answer_blocks'] = is_array($value['short_answer_blocks'] ?? null) ? $value['short_answer_blocks'] : [];
        $value['content_opportunities'] = is_array($value['content_opportunities'] ?? null) ? $value['content_opportunities'] : [];
        $value['technical_risks'] = is_array($value['technical_risks'] ?? null) ? $value['technical_risks'] : [];

        return $value;
    }

    /** @return array<string, mixed> */
    private function pagePayload(AuditPage $page): array
    {
        return [
            'url' => $page->getUrl(),
            'status_code' => $page->getStatusCode(),
            'content_type' => $page->getContentType(),
            'title' => $page->getTitle(),
            'meta_description' => $page->getMetaDescription(),
            'h1' => $page->getH1(),
            'canonical_url' => $page->getCanonicalUrl(),
            'robots_meta' => $page->getRobotsMeta(),
            'word_count' => $page->getWordCount(),
            'internal_links' => $page->getInternalLinksCount(),
            'external_links' => $page->getExternalLinksCount(),
            'images_without_alt' => $page->getImagesWithoutAltCount(),
            'load_time_ms' => $page->getLoadTimeMs(),
            'indexable' => $page->isIndexable(),
            'structured_data' => $page->hasStructuredData(),
            'error' => $page->getErrorMessage(),
        ];
    }

    /** @return array<string, mixed> */
    private function issuePayload(AuditIssue $issue): array
    {
        return [
            'type' => $issue->getIssueType(),
            'severity' => $issue->getSeverity(),
            'category' => $this->categoryForIssue($issue->getIssueType()),
            'message' => $issue->getMessage(),
            'recommendation' => $issue->getRecommendation(),
            'url' => $issue->getAuditPage()?->getUrl(),
        ];
    }

    private function categoryForIssue(string $issueType): string
    {
        if (str_contains($issueType, 'title') || str_contains($issueType, 'meta') || str_contains($issueType, 'canonical')) {
            return 'metadata';
        }

        if (str_contains($issueType, 'h1') || str_contains($issueType, 'heading')) {
            return 'headings';
        }

        if (str_contains($issueType, 'http') || str_contains($issueType, 'fetch')) {
            return 'crawl';
        }

        if (str_contains($issueType, 'slow')) {
            return 'performance';
        }

        if (str_contains($issueType, 'image') || str_contains($issueType, 'alt')) {
            return 'images';
        }

        if (str_contains($issueType, 'robots') || str_contains($issueType, 'noindex')) {
            return 'indexability';
        }

        if (str_contains($issueType, 'structured')) {
            return 'structured_data';
        }

        if (str_contains($issueType, 'link')) {
            return 'links';
        }

        return 'content';
    }

    private function percent(int $value, int $total): int
    {
        return max(0, min(100, (int) round(($value / max(1, $total)) * 100)));
    }

    private function scoreLabel(?int $score): string
    {
        if (null === $score) {
            return 'Pending';
        }

        if ($score < 40) {
            return 'Poor';
        }

        if ($score < 60) {
            return 'Needs improvement';
        }

        if ($score < 80) {
            return 'Good';
        }

        return 'Excellent';
    }
}
