<?php

declare(strict_types=1);

namespace App\Service\Pipeline;

use App\Dto\SerpResultDto;
use App\Entity\SerpAnalysis;
use App\Entity\TopicResearch;

final class SerpQuestionAnalyzerService
{
    public function __construct(private readonly PipelineClaudeClient $claudeClient) {}

    /** @param list<string> $suggestions */
    public function analyze(TopicResearch $topicResearch, SerpResultDto $serpResult, array $suggestions): SerpAnalysis
    {
        $result = $this->claudeClient->requestJson(
            $topicResearch,
            TopicResearch::STEP_SERP_ANALYSIS,
            $this->systemPrompt(),
            [
                'keyword' => $topicResearch->getPrimaryKeyword(),
                'country' => $topicResearch->getCountry(),
                'language' => $topicResearch->getLanguage(),
                'sector' => $topicResearch->getSector(),
                'audience' => $topicResearch->getAudience(),
                'business_objective' => $topicResearch->getBusinessObjective(),
                'serp' => $serpResult->toArray(),
                'suggestions' => $suggestions,
            ],
            12000,
            0.2,
        );

        $parsed = $result->parsedResponse;
        $questions = $this->listOfObjects($parsed['questions'] ?? []);
        $analysis = $topicResearch->getSerpAnalysis() ?? new SerpAnalysis();
        $analysis
            ->setTopicResearch($topicResearch)
            ->setCompetitors($this->listOfObjects($parsed['competitors'] ?? []))
            ->setSerpFeatures($this->object($parsed['serp_features'] ?? []))
            ->setContentGaps($this->listOfObjects($parsed['content_gaps'] ?? []))
            ->setQuestions($questions)
            ->setTotalQuestions(count($questions))
            ->setAverageWordCount($this->intValue($parsed['average_word_count'] ?? 0))
            ->setRawSerpResponse([
                'provider' => 'zenserp',
                'serp' => $serpResult->toArray(),
                'suggestions' => $suggestions,
            ])
            ->setAnalyzedAt(new \DateTimeImmutable());

        return $analysis;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are a SERP and search-question analyst for an asynchronous editorial pipeline.
Analyze the provided normalized search results and suggestions. Do not invent live data that is not present in the payload.
Return only one valid JSON object with this schema:
{
  "competitors": [{"url": "string", "title": "string", "h1": "string|null", "h2s": ["string"], "word_count": 0, "structure": ["string"], "faq": ["string"], "tables": ["string"], "media": ["string"]}],
  "serp_features": {"featured_snippets": [], "paa": [], "related_searches": [], "images": [], "videos": []},
  "content_gaps": [{"topic": "string", "coverage": "missing|thin|strong", "opportunity_score": 0}],
  "questions": [{"question": "string", "intent": "informational|commercial|transactional|navigational", "cluster": "string", "priority_score": 0, "source": "paa|suggest|related"}],
  "average_word_count": 0
}
Prioritize questions from people-also-ask, suggestions, and related searches. Score priority from 0 to 10.
PROMPT;
    }

    /** @return array<int, array<string, mixed>> */
    private function listOfObjects(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /** @return array<string, mixed> */
    private function object(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? max(0, (int) $value) : 0;
    }
}
