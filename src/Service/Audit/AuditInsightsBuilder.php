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
        $pageIssueCounts = $this->pageIssueCounts($issues);

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
            'site_content_signals' => $this->siteContentSignals($pages),
            'keyword_evidence' => $this->keywordEvidence($pages),
            'duplicate_metadata' => $this->duplicateMetadata($pages),
            'top_pages_by_issue_count' => array_slice($insights['top_pages'], 0, 8),
            'all_pages_compact' => [
                'included_pages' => min(count($pages), 120),
                'total_crawled_pages' => count($pages),
                'pages' => array_map(
                    fn (AuditPage $page): array => $this->compactPagePayload($page, $pageIssueCounts[spl_object_id($page)] ?? 0),
                    array_slice($pages, 0, 120),
                ),
            ],
            'sample_pages' => array_map(
                fn (AuditPage $page): array => $this->pagePayload($page),
                array_slice($pages, 0, 25),
            ),
            'top_issues' => array_map(
                fn (AuditIssue $issue): array => $this->issuePayload($issue),
                array_slice($issues, 0, 50),
            ),
            'analysis_limitations' => [
                'The crawler stores compact page excerpts, not full page HTML.',
                'Keyword evidence is derived from crawled URLs, titles, meta descriptions, headings, and stored body excerpts only.',
                'No search volume, keyword difficulty, backlinks, competitor rankings, or conversion data is available unless present in the crawl facts.',
                'The crawler does not execute JavaScript, so JS-rendered content may be underrepresented.',
                'Only pages inside the configured crawl limits are available for site-wide conclusions.',
            ],
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

    /**
     * @param list<AuditIssue> $issues
     *
     * @return array<int, int>
     */
    private function pageIssueCounts(array $issues): array
    {
        $pageIssueCounts = [];

        foreach ($issues as $issue) {
            $page = $issue->getAuditPage();
            if ($page instanceof AuditPage) {
                $pageIssueCounts[spl_object_id($page)] = ($pageIssueCounts[spl_object_id($page)] ?? 0) + 1;
            }
        }

        return $pageIssueCounts;
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
                'keyword_analysis' => [],
                'technical_seo' => [],
                'on_page_seo' => [],
                'content_strategy' => [],
                'geo_analysis' => [],
                'serp_features' => [],
                'priority_matrix' => [],
                'action_plan_30_60_90' => [],
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
        $value['executive_summary'] = is_array($value['executive_summary'] ?? null) ? $value['executive_summary'] : [];
        $value['audience_and_intent'] = is_array($value['audience_and_intent'] ?? null) ? $value['audience_and_intent'] : [];
        $value['keyword_analysis'] = is_array($value['keyword_analysis'] ?? null) ? $value['keyword_analysis'] : [];
        $value['technical_seo'] = is_array($value['technical_seo'] ?? null) ? $value['technical_seo'] : [];
        $value['on_page_seo'] = is_array($value['on_page_seo'] ?? null) ? $value['on_page_seo'] : [];
        $value['content_strategy'] = is_array($value['content_strategy'] ?? null) ? $value['content_strategy'] : [];
        $value['geo_analysis'] = is_array($value['geo_analysis'] ?? null) ? $value['geo_analysis'] : [];
        $value['serp_features'] = is_array($value['serp_features'] ?? null) ? $value['serp_features'] : [];
        $value['priority_matrix'] = is_array($value['priority_matrix'] ?? null) ? $value['priority_matrix'] : [];
        $value['action_plan_30_60_90'] = is_array($value['action_plan_30_60_90'] ?? null) ? $value['action_plan_30_60_90'] : [];

        return $value;
    }

    /** @return array<string, mixed> */
    private function pagePayload(AuditPage $page): array
    {
        $metadata = $page->getMetadata() ?? [];

        return [
            'url' => $page->getUrl(),
            'status_code' => $page->getStatusCode(),
            'content_type' => $page->getContentType(),
            'title' => $page->getTitle(),
            'title_length' => $metadata['title_length'] ?? (null === $page->getTitle() ? 0 : strlen($page->getTitle())),
            'meta_description' => $page->getMetaDescription(),
            'meta_description_length' => $metadata['meta_description_length'] ?? (null === $page->getMetaDescription() ? 0 : strlen($page->getMetaDescription())),
            'h1' => $page->getH1(),
            'headings' => $metadata['headings'] ?? [],
            'canonical_url' => $page->getCanonicalUrl(),
            'robots_meta' => $page->getRobotsMeta(),
            'word_count' => $page->getWordCount(),
            'internal_links' => $page->getInternalLinksCount(),
            'external_links' => $page->getExternalLinksCount(),
            'images_without_alt' => $page->getImagesWithoutAltCount(),
            'load_time_ms' => $page->getLoadTimeMs(),
            'indexable' => $page->isIndexable(),
            'structured_data' => $page->hasStructuredData(),
            'structured_data_types' => $metadata['structured_data_types'] ?? [],
            'language' => $metadata['language'] ?? null,
            'viewport_meta_present' => $metadata['viewport_meta_present'] ?? null,
            'image_count' => $metadata['image_count'] ?? null,
            'paragraph_count' => $metadata['paragraph_count'] ?? null,
            'list_count' => $metadata['list_count'] ?? null,
            'open_graph' => $metadata['open_graph'] ?? [],
            'twitter' => $metadata['twitter'] ?? [],
            'top_terms' => $metadata['top_terms'] ?? [],
            'body_excerpt' => $metadata['body_excerpt'] ?? null,
            'html_size_bytes' => $metadata['html_size_bytes'] ?? null,
            'error' => $page->getErrorMessage(),
        ];
    }

    private function compactPagePayload(AuditPage $page, int $issueCount): array
    {
        $metadata = $page->getMetadata() ?? [];

        return [
            'url' => $page->getUrl(),
            'status_code' => $page->getStatusCode(),
            'issue_count' => $issueCount,
            'title' => $page->getTitle(),
            'title_length' => $metadata['title_length'] ?? (null === $page->getTitle() ? 0 : strlen($page->getTitle())),
            'meta_description_length' => $metadata['meta_description_length'] ?? (null === $page->getMetaDescription() ? 0 : strlen($page->getMetaDescription())),
            'h1' => $page->getH1(),
            'word_count' => $page->getWordCount(),
            'indexable' => $page->isIndexable(),
            'canonical_url' => $page->getCanonicalUrl(),
            'load_time_ms' => $page->getLoadTimeMs(),
            'images_without_alt' => $page->getImagesWithoutAltCount(),
            'structured_data_types' => $metadata['structured_data_types'] ?? [],
            'top_terms' => array_slice(is_array($metadata['top_terms'] ?? null) ? $metadata['top_terms'] : [], 0, 8),
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

    /** @param list<AuditPage> $pages */
    private function siteContentSignals(array $pages): array
    {
        $termCounts = [];
        $titles = [];
        $h1s = [];
        $pagesWithBodyExcerpt = 0;

        foreach ($pages as $page) {
            $metadata = $page->getMetadata() ?? [];
            if (null !== $page->getTitle()) {
                $titles[] = $page->getTitle();
            }

            if (null !== $page->getH1()) {
                array_push($h1s, ...array_filter(array_map('trim', explode("\n", $page->getH1()))));
            }

            if (is_string($metadata['body_excerpt'] ?? null) && '' !== $metadata['body_excerpt']) {
                ++$pagesWithBodyExcerpt;
            }

            foreach (is_array($metadata['top_terms'] ?? null) ? $metadata['top_terms'] : [] as $term) {
                if (!is_array($term) || !is_scalar($term['term'] ?? null) || !is_numeric($term['count'] ?? null)) {
                    continue;
                }

                $keyword = strtolower((string) $term['term']);
                $termCounts[$keyword] = ($termCounts[$keyword] ?? 0) + (int) $term['count'];
            }
        }

        arsort($termCounts);

        $aggregateTerms = [];
        foreach (array_slice($termCounts, 0, 30, true) as $term => $count) {
            $aggregateTerms[] = [
                'term' => $term,
                'count' => $count,
            ];
        }

        return [
            'unique_titles' => array_values(array_unique(array_slice($titles, 0, 40))),
            'unique_h1s' => array_values(array_unique(array_slice($h1s, 0, 40))),
            'aggregate_top_terms' => $aggregateTerms,
            'pages_with_body_excerpt' => $pagesWithBodyExcerpt,
            'body_excerpt_coverage' => $this->percent($pagesWithBodyExcerpt, max(1, count($pages))),
        ];
    }

    /** @param list<AuditPage> $pages */
    private function keywordEvidence(array $pages): array
    {
        $signals = $this->siteContentSignals($pages);
        $keywords = [];

        foreach (array_slice($signals['aggregate_top_terms'], 0, 20) as $termData) {
            if (!is_array($termData) || !is_scalar($termData['term'] ?? null)) {
                continue;
            }

            $term = (string) $termData['term'];
            $placements = [
                'url' => 0,
                'title' => 0,
                'meta_description' => 0,
                'h1' => 0,
                'body_excerpt' => 0,
            ];
            $examplePages = [];

            foreach ($pages as $page) {
                $metadata = $page->getMetadata() ?? [];
                if ($this->containsTerm($page->getUrl(), $term)) {
                    ++$placements['url'];
                }

                if ($this->containsTerm($page->getTitle(), $term)) {
                    ++$placements['title'];
                }

                if ($this->containsTerm($page->getMetaDescription(), $term)) {
                    ++$placements['meta_description'];
                }

                if ($this->containsTerm($page->getH1(), $term)) {
                    ++$placements['h1'];
                }

                if ($this->containsTerm(is_string($metadata['body_excerpt'] ?? null) ? $metadata['body_excerpt'] : null, $term)) {
                    ++$placements['body_excerpt'];
                }

                if (count($examplePages) < 5 && (
                    $this->containsTerm($page->getTitle(), $term)
                    || $this->containsTerm($page->getH1(), $term)
                    || $this->containsTerm(is_string($metadata['body_excerpt'] ?? null) ? $metadata['body_excerpt'] : null, $term)
                )) {
                    $examplePages[] = $page->getUrl();
                }
            }

            $keywords[] = [
                'keyword_candidate' => $term,
                'total_occurrences_in_stored_excerpts' => $termData['count'] ?? null,
                'pages_observed' => count($examplePages),
                'placements' => $placements,
                'example_pages' => $examplePages,
            ];
        }

        return [
            'detected_keyword_candidates' => $keywords,
            'evidence_limitations' => 'These candidates come from compact crawled excerpts and on-page metadata. They are not search-volume or rank-tracking data.',
        ];
    }

    /** @param list<AuditPage> $pages */
    private function duplicateMetadata(array $pages): array
    {
        return [
            'titles' => $this->duplicatePageValues($pages, static fn (AuditPage $page): ?string => $page->getTitle()),
            'meta_descriptions' => $this->duplicatePageValues($pages, static fn (AuditPage $page): ?string => $page->getMetaDescription()),
            'h1s' => $this->duplicatePageValues($pages, static fn (AuditPage $page): ?string => $page->getH1()),
        ];
    }

    /**
     * @param list<AuditPage> $pages
     *
     * @return list<array{value: string, count: int, pages: list<string>}>
     */
    private function duplicatePageValues(array $pages, \Closure $valueAccessor): array
    {
        $values = [];

        foreach ($pages as $page) {
            $value = $valueAccessor($page);
            if (!is_string($value) || '' === trim($value)) {
                continue;
            }

            $key = strtolower(trim((string) preg_replace('/\s+/', ' ', $value)));
            $values[$key]['value'] ??= trim($value);
            $values[$key]['pages'][] = $page->getUrl();
        }

        $duplicates = [];
        foreach ($values as $value) {
            $pagesForValue = array_values(array_unique($value['pages']));
            if (count($pagesForValue) < 2) {
                continue;
            }

            $duplicates[] = [
                'value' => $value['value'],
                'count' => count($pagesForValue),
                'pages' => array_slice($pagesForValue, 0, 8),
            ];
        }

        usort($duplicates, static fn (array $left, array $right): int => $right['count'] <=> $left['count']);

        return array_slice($duplicates, 0, 12);
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

    private function containsTerm(?string $text, string $term): bool
    {
        if (null === $text || '' === $text) {
            return false;
        }

        $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
        $term = function_exists('mb_strtolower') ? mb_strtolower($term, 'UTF-8') : strtolower($term);

        return str_contains($text, $term);
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
