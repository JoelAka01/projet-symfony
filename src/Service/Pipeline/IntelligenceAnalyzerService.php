<?php

declare(strict_types=1);

namespace App\Service\Pipeline;

use App\Entity\IntelligenceAnalysis;
use App\Entity\SerpAnalysis;
use App\Entity\TopicResearch;

final class IntelligenceAnalyzerService
{
    public function __construct(private readonly PipelineClaudeClient $claudeClient) {}

    public function analyze(TopicResearch $topicResearch, SerpAnalysis $serpAnalysis): IntelligenceAnalysis
    {
        $result = $this->claudeClient->requestJson(
            $topicResearch,
            TopicResearch::STEP_INTELLIGENCE,
            $this->systemPrompt(),
            [
                'keyword' => $topicResearch->getPrimaryKeyword(),
                'country' => $topicResearch->getCountry(),
                'language' => $topicResearch->getLanguage(),
                'sector' => $topicResearch->getSector(),
                'audience' => $topicResearch->getAudience(),
                'business_objective' => $topicResearch->getBusinessObjective(),
                'competitors' => $serpAnalysis->getCompetitors(),
                'questions' => $serpAnalysis->getQuestions(),
                'content_gaps' => $serpAnalysis->getContentGaps(),
                'serp_features' => $serpAnalysis->getSerpFeatures(),
            ],
            12000,
            0.2,
        );

        $parsed = $result->parsedResponse;
        $analysis = $topicResearch->getIntelligenceAnalysis() ?? new IntelligenceAnalysis();
        $analysis
            ->setTopicResearch($topicResearch)
            ->setPrimaryIntent($this->stringValue($parsed['primary_intent'] ?? 'informational'))
            ->setIntentBreakdown($this->numericMap($parsed['intent_breakdown'] ?? []))
            ->setEntities($this->listOfObjects($parsed['entities'] ?? []))
            ->setSemanticConcepts($this->listOfObjects($parsed['semantic_concepts'] ?? []))
            ->setAnalyzedAt(new \DateTimeImmutable());

        return $analysis;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are an intent, entity, and semantic analyst for an editorial generation pipeline.
Use only the supplied keyword, SERP competitor summaries, questions, and content gaps. Do not claim access to live search data beyond the payload.
Return only one valid JSON object with this schema:
{
  "primary_intent": "informational|commercial|transactional|navigational",
  "intent_breakdown": {"informational": 0.0, "commercial": 0.0, "transactional": 0.0, "navigational": 0.0},
  "entities": [{"name": "string", "type": "brand|product|concept|person|place|organization|metric|regulation|other", "relevance_score": 0, "relations": ["string"]}],
  "semantic_concepts": [{"concept": "string", "cooccurrences": ["string"], "synonyms": ["string"]}]
}
Scores must be 0 to 10 for relevance_score and 0 to 1 for intent_breakdown values.
PROMPT;
    }

    private function stringValue(mixed $value): string
    {
        if (!is_scalar($value) || '' === trim((string) $value)) {
            return '';
        }

        return trim((string) $value);
    }

    /** @return array<string, float|int> */
    private function numericMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $map = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && is_numeric($item)) {
                $map[$key] = (float) $item;
            }
        }

        return $map;
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
}
