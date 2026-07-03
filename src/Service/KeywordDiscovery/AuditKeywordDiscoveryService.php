<?php

declare(strict_types=1);

namespace App\Service\KeywordDiscovery;

use App\Entity\KeywordSuggestion;
use App\Entity\Project;
use App\Enum\KeywordSuggestionSource;
use App\Enum\PipelineQualityMode;
use App\Repository\ArticleRepository;
use App\Repository\AuditRepository;
use App\Repository\KeywordRepository;
use App\Repository\KeywordSuggestionRepository;
use App\Service\Cost\ApiCostGuard;
use App\Service\Cost\ApiUsageLogger;
use App\Service\Serp\SerpProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AuditKeywordDiscoveryService
{
    private const MIN_EXPLOITABLE_SUGGESTIONS = 10;
    private const AUDIT_TARGET_KEYWORD_THRESHOLD = 20;
    private const AUDIT_COMPETITOR_TERM_THRESHOLD = 30;

    /** @var list<string> */
    private const TERM_KEYS = ['term', 'keyword', 'query', 'text', 'title', 'name', 'topic', 'label'];

    /** @var list<string> */
    private const QUESTION_KEYS = ['question', 'query', 'text', 'title'];

    /** @var list<string> */
    private const STOP_WORDS = [
        'a', 'au', 'aux', 'avec', 'ce', 'ces', 'cette', 'dans', 'de', 'des', 'du', 'en', 'et',
        'la', 'le', 'les', 'l', 'd', 'pour', 'sur', 'un', 'une', 'vos', 'votre', 'nos', 'notre',
        'the', 'and', 'or', 'for', 'with', 'to', 'of', 'in', 'on',
    ];

    public function __construct(
        private readonly AuditRepository $auditRepository,
        private readonly KeywordRepository $keywordRepository,
        private readonly KeywordSuggestionRepository $keywordSuggestionRepository,
        private readonly ArticleRepository $articleRepository,
        private readonly SerpProviderInterface $serpProvider,
        private readonly EntityManagerInterface $entityManager,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly ApiCostGuard $apiCostGuard,
        private readonly ApiUsageLogger $apiUsageLogger,
    ) {}

    /**
     * @return array{
     *     audit_used: bool,
     *     fallback_used: bool,
     *     created: int,
     *     updated: int,
     *     skipped: int,
     *     suggestions: list<KeywordSuggestion>
     * }
     */
    public function discover(Project $project, bool $allowFallback = false): array
    {
        $audit = $this->auditRepository->findLatestCompletedForProject($project);
        if (null === $audit) {
            return [
                'audit_used' => false,
                'fallback_used' => false,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'suggestions' => $this->keywordSuggestionRepository->findForProject($project),
            ];
        }

        $analysis = $this->analysisFromMetadata($audit->getMetadata() ?? []);
        $auditCandidates = $this->extractAuditCandidates($analysis);
        $auditCounts = $this->auditCounts($analysis);
        $summary = $this->persistCandidates($project, $auditCandidates);
        $fallbackUsed = false;

        $auditHasEnoughRawData = $auditCounts['detected_keywords'] >= self::AUDIT_TARGET_KEYWORD_THRESHOLD
            && $auditCounts['competitor_terms'] >= self::AUDIT_COMPETITOR_TERM_THRESHOLD;
        $usableSuggestions = count($this->keywordSuggestionRepository->findForProject($project, self::MIN_EXPLOITABLE_SUGGESTIONS));

        if ($allowFallback && !$auditHasEnoughRawData && $usableSuggestions < self::MIN_EXPLOITABLE_SUGGESTIONS) {
            $fallbackCandidates = $this->fallbackCandidates($project, $analysis);
            if ([] !== $fallbackCandidates) {
                $fallbackSummary = $this->persistCandidates($project, $fallbackCandidates);
                $summary['created'] += $fallbackSummary['created'];
                $summary['updated'] += $fallbackSummary['updated'];
                $summary['skipped'] += $fallbackSummary['skipped'];
                $fallbackUsed = true;
            }
        }

        $this->entityManager->flush();

        return [
            'audit_used' => true,
            'fallback_used' => $fallbackUsed,
            'created' => $summary['created'],
            'updated' => $summary['updated'],
            'skipped' => $summary['skipped'],
            'suggestions' => $this->keywordSuggestionRepository->findForProject($project),
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     *
     * @return array<string, mixed>
     */
    private function analysisFromMetadata(array $metadata): array
    {
        $analysis = $metadata['ai_analysis'] ?? $metadata['analysis'] ?? $metadata;

        return is_array($analysis) ? $analysis : [];
    }

    /**
     * @param array<string, mixed> $analysis
     *
     * @return list<array{
     *     term: string,
     *     source: KeywordSuggestionSource,
     *     frequency: int,
     *     raw_data: array<string, mixed>
     * }>
     */
    private function extractAuditCandidates(array $analysis): array
    {
        $candidates = [];

        foreach ($this->termsFromValues($this->valuesForKeys($analysis, ['detected_target_keywords']), self::TERM_KEYS) as $item) {
            $this->addCandidate($candidates, $item['term'], KeywordSuggestionSource::AUDIT_DETECTED_KEYWORD, $item['raw_data']);
        }

        foreach ($this->termsFromValues($this->valuesAtPath($analysis, ['keyword_analysis', 'detected_target_keywords']), self::TERM_KEYS) as $item) {
            $this->addCandidate($candidates, $item['term'], KeywordSuggestionSource::AUDIT_DETECTED_KEYWORD, $item['raw_data']);
        }

        foreach ($this->termsFromValues($this->valuesForKeys($analysis, ['competitor_keywords', 'competitor_terms']), self::TERM_KEYS) as $item) {
            $frequency = $this->frequencyFromRawData($item['raw_data']);
            $this->addCandidate($candidates, $item['term'], KeywordSuggestionSource::COMPETITOR_FREQUENT_TERM, $item['raw_data'], $frequency);
        }

        foreach ($this->termsFromValues($this->valuesForKeys($analysis, ['questions', 'faq', 'people_also_ask']), self::QUESTION_KEYS) as $item) {
            $this->addCandidate($candidates, $item['term'], KeywordSuggestionSource::AUDIT_QUESTION, $item['raw_data']);
        }

        foreach ($this->termsFromValues($this->valuesForKeys($analysis, ['content_gaps', 'suggested_topics']), self::TERM_KEYS) as $item) {
            $this->addCandidate($candidates, $item['term'], KeywordSuggestionSource::CONTENT_GAP, $item['raw_data']);
        }

        foreach ($this->termsFromValues($this->valuesForKeys($analysis, ['related_searches']), self::TERM_KEYS) as $item) {
            $this->addCandidate($candidates, $item['term'], KeywordSuggestionSource::SERP_SUGGEST, $item['raw_data']);
        }

        return array_values($candidates);
    }

    /**
     * @param array<string, mixed> $analysis
     *
     * @return array{detected_keywords: int, competitor_terms: int}
     */
    private function auditCounts(array $analysis): array
    {
        $detected = array_merge(
            $this->termsFromValues($this->valuesForKeys($analysis, ['detected_target_keywords']), self::TERM_KEYS),
            $this->termsFromValues($this->valuesAtPath($analysis, ['keyword_analysis', 'detected_target_keywords']), self::TERM_KEYS),
        );
        $competitors = $this->termsFromValues($this->valuesForKeys($analysis, ['competitor_keywords', 'competitor_terms']), self::TERM_KEYS);

        return [
            'detected_keywords' => $this->countUniqueTerms($detected),
            'competitor_terms' => $this->countUniqueTerms($competitors),
        ];
    }

    /**
     * @param list<array{term: string, raw_data: array<string, mixed>}> $items
     */
    private function countUniqueTerms(array $items): int
    {
        $terms = [];
        foreach ($items as $item) {
            $normalized = $this->normalizeTerm($item['term']);
            if ('' !== $normalized) {
                $terms[$normalized] = true;
            }
        }

        return count($terms);
    }

    /**
     * @param list<array{
     *     term: string,
     *     source: KeywordSuggestionSource,
     *     frequency: int,
     *     raw_data: array<string, mixed>
     * }> $candidates
     *
     * @return array{created: int, updated: int, skipped: int}
     */
    private function persistCandidates(Project $project, array $candidates): array
    {
        $summary = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $existingKeywords = $this->normalizedExistingKeywords($project);
        $existingArticles = $this->normalizedExistingArticles($project);
        $existingSuggestions = $this->existingSuggestionsByNormalizedTerm($project);
        $blockedSignatures = $this->existingSignatures($existingKeywords + $existingArticles);
        $seenSignatures = [];

        foreach ($candidates as $candidate) {
            $term = $this->cleanTerm($candidate['term']);
            $normalized = $this->normalizeTerm($term);
            if (!$this->isUsableTerm($term, $normalized)) {
                ++$summary['skipped'];
                continue;
            }

            $signature = $this->termSignature($normalized);
            if (isset($existingKeywords[$normalized]) || isset($existingArticles[$normalized]) || isset($blockedSignatures[$signature])) {
                ++$summary['skipped'];
                continue;
            }

            $existingSuggestion = $existingSuggestions[$normalized] ?? null;
            if (null === $existingSuggestion && (isset($seenSignatures[$signature]) || $this->hasCloseSignature($signature, $seenSignatures))) {
                ++$summary['skipped'];
                continue;
            }

            $score = $this->scoreCandidate($term, $candidate['source'], $candidate['frequency']);
            $suggestion = $existingSuggestion ?? new KeywordSuggestion();
            $suggestion
                ->setProject($project)
                ->setTerm($term)
                ->setNormalizedTerm($normalized)
                ->setSource($candidate['source'])
                ->setIntent($score['intent'])
                ->setClusterName($this->clusterName($term, $candidate['source']))
                ->setBusinessScore($score['business_score'])
                ->setDifficultyEstimate($score['difficulty_estimate'])
                ->setOpportunityScore($score['opportunity_score'])
                ->setSearchVolumeEstimate($score['search_volume_estimate'])
                ->setRawData([
                    'source' => $candidate['source']->value,
                    'frequency' => $candidate['frequency'],
                    'raw' => $candidate['raw_data'],
                    'score' => $score,
                ]);

            if (null === $existingSuggestion) {
                $this->entityManager->persist($suggestion);
                $existingSuggestions[$normalized] = $suggestion;
                ++$summary['created'];
            } else {
                $suggestion->touch();
                ++$summary['updated'];
            }

            $seenSignatures[$signature] = true;
        }

        return $summary;
    }

    /**
     * @param array<string, mixed> $analysis
     *
     * @return list<array{
     *     term: string,
     *     source: KeywordSuggestionSource,
     *     frequency: int,
     *     raw_data: array<string, mixed>
     * }>
     */
    private function fallbackCandidates(Project $project, array $analysis): array
    {
        $candidates = [];
        $seeds = $this->claudeSeeds($project, $analysis);

        foreach ($seeds as $seed) {
            $this->addCandidate($candidates, $seed, KeywordSuggestionSource::AI_GENERATED, ['seed' => $seed]);

            try {
                if (!$this->apiCostGuard->shouldCallExternalApi($project, 'keyword_discovery_serp_suggest', [
                    'mode' => PipelineQualityMode::BALANCED,
                    'operation_type' => 'serp',
                    'estimated_cost' => 0.003,
                ])) {
                    continue;
                }

                $suggestions = $this->serpProvider->suggest(
                    $seed,
                    $project->getTargetCountry() ?? 'FR',
                    $project->getDefaultLanguage() ?? 'fr',
                );
                $this->apiUsageLogger->log($project, 'zenserp', 'keyword_discovery_serp_suggest', estimatedCost: 0.003);
            } catch (\Throwable $exception) {
                $this->logger->warning('Keyword discovery SERP fallback failed.', [
                    'project_id' => $project->getId(),
                    'seed' => $seed,
                    'error' => $exception->getMessage(),
                ]);
                continue;
            }

            foreach (array_slice($suggestions, 0, 20) as $suggestion) {
                $this->addCandidate($candidates, $suggestion, KeywordSuggestionSource::SERP_SUGGEST, [
                    'seed' => $seed,
                    'suggestion' => $suggestion,
                ]);
            }
        }

        return array_values($candidates);
    }

    /**
     * @param array<string, mixed> $analysis
     *
     * @return list<string>
     */
    private function claudeSeeds(Project $project, array $analysis): array
    {
        $apiKey = $this->envString('CLAUDE_API_KEY');
        if (null === $apiKey) {
            $this->logger->info('Keyword discovery Claude fallback skipped because CLAUDE_API_KEY is not configured.', [
                'project_id' => $project->getId(),
            ]);

            return [];
        }

        $model = $this->envString('CLAUDE_MODEL') ?? 'claude-haiku-4-5-20251001';
        $baseUrl = rtrim($this->envString('CLAUDE_API_BASE_URL') ?? 'https://api.anthropic.com', '/');
        $payload = [
            'project_name' => $project->getName(),
            'country' => $project->getTargetCountry() ?? 'FR',
            'language' => $project->getDefaultLanguage() ?? 'fr',
            'audit_extract' => $this->compactAnalysis($analysis),
        ];

        try {
            $prompt = json_encode($payload, JSON_THROW_ON_ERROR);
            if (!$this->apiCostGuard->shouldCallExternalApi($project, 'keyword_discovery_claude_seed', [
                'mode' => PipelineQualityMode::BALANCED,
                'operation_type' => 'ai',
                'estimated_tokens' => (int) ceil(mb_strlen($prompt) / 4) + 800,
                'estimated_cost' => 0.01,
            ])) {
                return [];
            }

            $response = $this->httpClient->request('POST', $baseUrl . '/v1/messages', [
                'headers' => [
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'max_tokens' => 800,
                    'temperature' => 0.2,
                    'system' => 'Return strict JSON only. Generate 5 to 10 concise seed keywords for economical SEO keyword discovery.',
                    'messages' => [[
                        'role' => 'user',
                        'content' => [[
                            'type' => 'text',
                            'text' => $prompt,
                        ]],
                    ]],
                ],
                'timeout' => 30,
                'max_duration' => 45,
            ]);

            $body = $response->getContent(false);
            if ($response->getStatusCode() >= 400) {
                throw new \RuntimeException(sprintf('Claude returned HTTP %d: %s', $response->getStatusCode(), mb_substr(strip_tags($body), 0, 500)));
            }

            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                return [];
            }

            $text = $this->extractClaudeText($decoded);
            $json = $this->parseJsonObject($text);
            $seeds = $json['seeds'] ?? $json['keywords'] ?? [];
            $this->apiUsageLogger->log($project, 'anthropic', 'keyword_discovery_claude_seed', estimatedCost: 0.01);

            return array_slice($this->scalarStrings($seeds), 0, 10);
        } catch (\Throwable $exception) {
            $this->logger->warning('Keyword discovery Claude fallback failed.', [
                'project_id' => $project->getId(),
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string>         $keys
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

    /**
     * @param array<string, mixed> $data
     * @param list<string>         $path
     *
     * @return list<mixed>
     */
    private function valuesAtPath(array $data, array $path): array
    {
        $current = $data;
        foreach ($path as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return [];
            }

            $current = $current[$key];
        }

        return [$current];
    }

    /**
     * @param list<mixed>  $values
     * @param list<string> $preferredKeys
     *
     * @return list<array{term: string, raw_data: array<string, mixed>}>
     */
    private function termsFromValues(array $values, array $preferredKeys): array
    {
        $terms = [];
        foreach ($values as $value) {
            array_push($terms, ...$this->termsFromMixed($value, $preferredKeys));
        }

        return $terms;
    }

    /**
     * @param list<string> $preferredKeys
     *
     * @return list<array{term: string, raw_data: array<string, mixed>}>
     */
    private function termsFromMixed(mixed $value, array $preferredKeys): array
    {
        if (is_scalar($value)) {
            $term = trim((string) $value);

            return '' === $term ? [] : [['term' => $term, 'raw_data' => ['value' => $term]]];
        }

        if (!is_array($value)) {
            return [];
        }

        foreach ($preferredKeys as $key) {
            $candidate = $value[$key] ?? null;
            if (is_scalar($candidate) && '' !== trim((string) $candidate)) {
                return [['term' => trim((string) $candidate), 'raw_data' => $this->stringKeyArray($value)]];
            }
        }

        $terms = [];
        if (!array_is_list($value)) {
            foreach ($value as $key => $item) {
                if (is_string($key) && is_numeric($item)) {
                    $terms[] = ['term' => $key, 'raw_data' => ['value' => $key, 'frequency' => (int) $item]];
                    continue;
                }

                array_push($terms, ...$this->termsFromMixed($item, $preferredKeys));
            }

            return $terms;
        }

        foreach ($value as $item) {
            array_push($terms, ...$this->termsFromMixed($item, $preferredKeys));
        }

        return $terms;
    }

    /**
     * @param array<string|int, mixed> $raw
     *
     * @return array<string, mixed>
     */
    private function stringKeyArray(array $raw): array
    {
        $data = [];
        foreach ($raw as $key => $value) {
            $data[(string) $key] = $value;
        }

        return $data;
    }

    /**
     * @param array<string, array{
     *     term: string,
     *     source: KeywordSuggestionSource,
     *     frequency: int,
     *     raw_data: array<string, mixed>
     * }> $candidates
     * @param array<string, mixed> $rawData
     */
    private function addCandidate(
        array &$candidates,
        string $term,
        KeywordSuggestionSource $source,
        array $rawData,
        int $frequency = 1,
    ): void {
        $term = $this->cleanTerm($term);
        $normalized = $this->normalizeTerm($term);
        if ('' === $normalized) {
            return;
        }

        $existing = $candidates[$normalized] ?? null;
        if (null !== $existing) {
            $candidates[$normalized]['frequency'] = max($existing['frequency'], $frequency) + 1;

            return;
        }

        $candidates[$normalized] = [
            'term' => $term,
            'source' => $source,
            'frequency' => max(1, $frequency),
            'raw_data' => $rawData,
        ];
    }

    /**
     * @return array{
     *     intent: string,
     *     business_score: int,
     *     difficulty_estimate: int,
     *     opportunity_score: int,
     *     search_volume_estimate: int|null
     * }
     */
    private function scoreCandidate(string $term, KeywordSuggestionSource $source, int $frequency): array
    {
        $intent = $this->inferIntent($term);
        $businessScore = match ($intent) {
            'TRANSACTIONAL' => 45,
            'COMMERCIAL' => 36,
            default => 18,
        };

        if (KeywordSuggestionSource::CONTENT_GAP === $source) {
            $businessScore += 10;
        }

        if (KeywordSuggestionSource::COMPETITOR_FREQUENT_TERM === $source) {
            $businessScore += 5;
        }

        $competitorFrequencyScore = KeywordSuggestionSource::COMPETITOR_FREQUENT_TERM === $source ? min(25, $frequency * 4) : 0;
        $intentScore = match ($intent) {
            'TRANSACTIONAL' => 25,
            'COMMERCIAL' => 18,
            default => 8,
        };
        $contentGapScore = match ($source) {
            KeywordSuggestionSource::CONTENT_GAP => 20,
            KeywordSuggestionSource::AUDIT_QUESTION => 8,
            default => 0,
        };
        $difficultyEstimate = $this->difficultyEstimate($term, $source, $frequency);
        $opportunityScore = $this->clamp($businessScore + $competitorFrequencyScore + $intentScore + $contentGapScore - $difficultyEstimate);

        return [
            'intent' => $intent,
            'business_score' => $this->clamp($businessScore),
            'difficulty_estimate' => $difficultyEstimate,
            'opportunity_score' => $opportunityScore,
            'search_volume_estimate' => null,
        ];
    }

    private function inferIntent(string $term): string
    {
        $normalized = $this->normalizeTerm($term);
        if (preg_match('/\b(prix|tarif|devis|location|louer|acheter|commande|reservation|service|solution|prestataire|professionnel)\b/u', $normalized)) {
            return 'TRANSACTIONAL';
        }

        if (preg_match('/\b(comparatif|meilleur|avis|alternative|choisir|selection)\b/u', $normalized)) {
            return 'COMMERCIAL';
        }

        return 'INFORMATIONAL';
    }

    private function difficultyEstimate(string $term, KeywordSuggestionSource $source, int $frequency): int
    {
        $wordCount = max(1, count(preg_split('/\s+/', $this->normalizeTerm($term), -1, PREG_SPLIT_NO_EMPTY) ?: []));
        $difficulty = 38 - min(18, $wordCount * 3);

        if (KeywordSuggestionSource::COMPETITOR_FREQUENT_TERM === $source) {
            $difficulty += min(15, $frequency * 2);
        }

        if (KeywordSuggestionSource::AUDIT_QUESTION === $source) {
            $difficulty -= 8;
        }

        return $this->clamp($difficulty, 8, 65);
    }

    private function clusterName(string $term, KeywordSuggestionSource $source): string
    {
        if (KeywordSuggestionSource::AUDIT_QUESTION === $source) {
            return 'Questions';
        }

        $tokens = array_values(array_filter(
            explode(' ', $this->normalizeTerm($term)),
            static fn(string $token): bool => !in_array($token, self::STOP_WORDS, true),
        ));

        return ucfirst(implode(' ', array_slice($tokens, 0, 3))) ?: 'Audit opportunities';
    }

    /** @return array<string, true> */
    private function normalizedExistingKeywords(Project $project): array
    {
        $terms = [];
        foreach ($this->keywordRepository->findForProject($project) as $keyword) {
            $normalized = $this->normalizeTerm($keyword->getTerm());
            if ('' !== $normalized) {
                $terms[$normalized] = true;
            }
        }

        return $terms;
    }

    /** @return array<string, true> */
    private function normalizedExistingArticles(Project $project): array
    {
        $terms = [];
        foreach ($this->articleRepository->findTitlesAndSlugsForProject($project) as $row) {
            foreach ([$row['title'], $row['slug'] ?? null] as $value) {
                if (!is_string($value)) {
                    continue;
                }

                $normalized = $this->normalizeTerm(str_replace('-', ' ', $value));
                if ('' !== $normalized) {
                    $terms[$normalized] = true;
                }
            }
        }

        return $terms;
    }

    /** @return array<string, KeywordSuggestion> */
    private function existingSuggestionsByNormalizedTerm(Project $project): array
    {
        $suggestions = [];
        foreach ($this->keywordSuggestionRepository->findForProject($project, 500) as $suggestion) {
            $suggestions[$suggestion->getNormalizedTerm()] = $suggestion;
        }

        return $suggestions;
    }

    /**
     * @param array<string, true> $normalizedTerms
     *
     * @return array<string, true>
     */
    private function existingSignatures(array $normalizedTerms): array
    {
        $signatures = [];
        foreach (array_keys($normalizedTerms) as $term) {
            $signature = $this->termSignature($term);
            if ('' !== $signature) {
                $signatures[$signature] = true;
            }
        }

        return $signatures;
    }

    /** @param array<string, true> $seenSignatures */
    private function hasCloseSignature(string $signature, array $seenSignatures): bool
    {
        foreach (array_keys($seenSignatures) as $seen) {
            if ($seen === $signature) {
                return true;
            }

            $length = max(mb_strlen($seen), mb_strlen($signature));
            if ($length > 0 && $length <= 80 && levenshtein($seen, $signature) <= 2) {
                return true;
            }
        }

        return false;
    }

    private function termSignature(string $normalized): string
    {
        $tokens = preg_split('/\s+/', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = array_values(array_unique(array_filter(
            $tokens,
            static fn(string $token): bool => mb_strlen($token) > 2 && !in_array($token, self::STOP_WORDS, true),
        )));
        sort($tokens);

        return implode(' ', $tokens);
    }

    private function cleanTerm(string $term): string
    {
        return trim(preg_replace('/\s+/u', ' ', strip_tags($term)) ?? '');
    }

    private function normalizeTerm(string $term): string
    {
        $value = mb_strtolower(trim($term));
        if (function_exists('transliterator_transliterate')) {
            $transliterated = transliterator_transliterate('Any-Latin; Latin-ASCII', $value);
            if (is_string($transliterated)) {
                $value = $transliterated;
            }
        } else {
            $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($transliterated)) {
                $value = $transliterated;
            }
        }

        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    private function isUsableTerm(string $term, string $normalized): bool
    {
        if (mb_strlen($term) < 3 || mb_strlen($term) > 180) {
            return false;
        }

        if ('' === $normalized || str_contains($normalized, 'http')) {
            return false;
        }

        return !preg_match('/\b(cliquez ici|en savoir plus|ce produit|cette offre|ce lien)\b/u', $normalized);
    }

    /** @param array<string, mixed> $rawData */
    private function frequencyFromRawData(array $rawData): int
    {
        foreach (['frequency', 'count', 'occurrences', 'score'] as $key) {
            $value = $rawData[$key] ?? null;
            if (is_numeric($value)) {
                return max(1, (int) $value);
            }
        }

        return 1;
    }

    private function clamp(int $value, int $min = 0, int $max = 100): int
    {
        return max($min, min($max, $value));
    }

    /** @return list<string> */
    private function scalarStrings(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $strings = [];
        foreach ($value as $item) {
            if (is_scalar($item) && '' !== trim((string) $item)) {
                $strings[] = trim((string) $item);
            }
        }

        return array_values(array_unique($strings));
    }

    /**
     * @param array<string, mixed> $analysis
     *
     * @return array<string, mixed>
     */
    private function compactAnalysis(array $analysis): array
    {
        $keys = [
            'detected_target_keywords',
            'keyword_analysis',
            'competitor_keywords',
            'competitor_terms',
            'content_gaps',
            'questions',
            'faq',
            'people_also_ask',
            'related_searches',
            'suggested_topics',
        ];
        $compact = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $analysis)) {
                $compact[$key] = $analysis[$key];
            }
        }

        return $compact;
    }

    /** @param array<string, mixed> $responseData */
    private function extractClaudeText(array $responseData): string
    {
        $text = '';
        $content = $responseData['content'] ?? [];
        if (!is_array($content)) {
            return '';
        }

        foreach ($content as $block) {
            if (is_array($block) && 'text' === ($block['type'] ?? null) && is_scalar($block['text'] ?? null)) {
                $text .= (string) $block['text'];
            }
        }

        return trim($text);
    }

    /** @return array<string, mixed> */
    private function parseJsonObject(string $text): array
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if (false === $start || false === $end || $end < $start) {
            return [];
        }

        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function envString(string $name): ?string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);
        if (!is_scalar($value) || '' === trim((string) $value)) {
            return null;
        }

        return trim((string) $value);
    }
}
