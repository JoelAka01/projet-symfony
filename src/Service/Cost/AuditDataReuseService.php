<?php

declare(strict_types=1);

namespace App\Service\Cost;

use App\Entity\Project;
use App\Entity\SerpAnalysis;
use App\Entity\TopicResearch;
use App\Repository\AuditRepository;

final class AuditDataReuseService
{
    public function __construct(private readonly AuditRepository $auditRepository) {}

    public function canReuseForDiscovery(Project $project): bool
    {
        $data = $this->extract($project);

        return count($data['detected_target_keywords']) >= 10
            || count($data['competitor_terms']) >= 10
            || count($data['questions']) + count($data['content_gaps']) >= 6;
    }

    public function buildSerpAnalysis(TopicResearch $topicResearch): ?SerpAnalysis
    {
        $project = $topicResearch->getProject();
        if (null === $project) {
            return null;
        }

        $data = $this->extract($project);
        $qualityScore = $this->qualityScore($data);
        if ($qualityScore < 65) {
            return null;
        }

        $analysis = $topicResearch->getSerpAnalysis() ?? new SerpAnalysis();
        $analysis
            ->setTopicResearch($topicResearch)
            ->setCompetitors(array_map(
                static fn (string $term): array => [
                    'url' => null,
                    'title' => $term,
                    'h1' => $term,
                    'h2s' => [],
                    'word_count' => 0,
                    'structure' => [],
                    'faq' => [],
                    'tables' => [],
                    'media' => [],
                    'source' => 'audit',
                ],
                array_slice($data['competitor_terms'], 0, 20),
            ))
            ->setSerpFeatures([
                'featured_snippets' => [],
                'paa' => array_map(static fn (string $question): array => ['question' => $question, 'source' => 'audit'], $data['questions']),
                'related_searches' => $data['suggested_topics'],
                'images' => [],
                'videos' => [],
                'source' => 'audit_reuse',
            ])
            ->setContentGaps(array_map(
                static fn (string $gap): array => ['topic' => $gap, 'coverage' => 'missing', 'opportunity_score' => 7, 'source' => 'audit'],
                array_slice($data['content_gaps'], 0, 20),
            ))
            ->setQuestions(array_map(
                static fn (string $question): array => ['question' => $question, 'intent' => 'informational', 'cluster' => 'Audit questions', 'priority_score' => 7, 'source' => 'audit'],
                array_slice($data['questions'], 0, 30),
            ))
            ->setAverageWordCount(0)
            ->setRawSerpResponse([
                'provider' => 'audit_reuse',
                'quality_score' => $qualityScore,
                'detected_target_keywords' => $data['detected_target_keywords'],
                'competitor_terms' => $data['competitor_terms'],
                'content_gaps' => $data['content_gaps'],
                'questions' => $data['questions'],
                'faq' => $data['faq'],
                'entities' => $data['entities'],
                'suggested_topics' => $data['suggested_topics'],
            ])
            ->setAnalyzedAt(new \DateTimeImmutable());

        return $analysis;
    }

    /**
     * @return array{
     *     detected_target_keywords: list<string>,
     *     competitor_terms: list<string>,
     *     content_gaps: list<string>,
     *     questions: list<string>,
     *     faq: list<string>,
     *     entities: list<string>,
     *     suggested_topics: list<string>
     * }
     */
    public function extract(Project $project): array
    {
        $audit = $this->auditRepository->findLatestCompletedForProject($project);
        $metadata = $audit?->getMetadata() ?? [];
        $analysis = $metadata['ai_analysis'] ?? $metadata;
        if (!is_array($analysis)) {
            $analysis = [];
        }

        return [
            'detected_target_keywords' => $this->terms($this->valuesForKeys($analysis, ['detected_target_keywords'])),
            'competitor_terms' => $this->terms($this->valuesForKeys($analysis, ['competitor_terms', 'competitor_keywords'])),
            'content_gaps' => $this->terms($this->valuesForKeys($analysis, ['content_gaps'])),
            'questions' => $this->terms($this->valuesForKeys($analysis, ['questions', 'people_also_ask'])),
            'faq' => $this->terms($this->valuesForKeys($analysis, ['faq'])),
            'entities' => $this->terms($this->valuesForKeys($analysis, ['entities'])),
            'suggested_topics' => $this->terms($this->valuesForKeys($analysis, ['suggested_topics', 'related_searches'])),
        ];
    }

    /**
     * @param array{
     *     detected_target_keywords: list<string>,
     *     competitor_terms: list<string>,
     *     content_gaps: list<string>,
     *     questions: list<string>,
     *     faq: list<string>,
     *     entities: list<string>,
     *     suggested_topics: list<string>
     * } $data
     */
    private function qualityScore(array $data): int
    {
        $score = 0;
        $score += min(25, count($data['detected_target_keywords']) * 2);
        $score += min(25, count($data['competitor_terms']) * 2);
        $score += min(20, count($data['questions']) * 4);
        $score += min(15, count($data['content_gaps']) * 3);
        $score += min(15, count($data['entities']) * 2);

        return min(100, $score);
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string>        $keys
     *
     * @return list<mixed>
     */
    private function valuesForKeys(array $data, array $keys): array
    {
        $values = [];
        foreach ($data as $key => $value) {
            if (in_array($key, $keys, true)) {
                $values[] = $value;
            }

            if (is_array($value)) {
                array_push($values, ...$this->valuesForKeys($value, $keys));
            }
        }

        return $values;
    }

    /** @param list<mixed> $values */
    private function terms(array $values): array
    {
        $terms = [];
        foreach ($values as $value) {
            foreach ($this->termsFromMixed($value) as $term) {
                $normalized = mb_strtolower(trim($term));
                if ('' !== $normalized) {
                    $terms[$normalized] = $term;
                }
            }
        }

        return array_values($terms);
    }

    /** @return list<string> */
    private function termsFromMixed(mixed $value): array
    {
        if (is_scalar($value)) {
            $term = trim((string) $value);

            return '' === $term ? [] : [$term];
        }

        if (!is_array($value)) {
            return [];
        }

        foreach (['term', 'keyword', 'query', 'question', 'topic', 'text', 'title', 'name'] as $key) {
            $candidate = $value[$key] ?? null;
            if (is_scalar($candidate) && '' !== trim((string) $candidate)) {
                return [trim((string) $candidate)];
            }
        }

        $terms = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && is_numeric($item)) {
                $terms[] = $key;
                continue;
            }

            array_push($terms, ...$this->termsFromMixed($item));
        }

        return $terms;
    }
}
