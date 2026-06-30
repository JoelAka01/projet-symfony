<?php

declare(strict_types=1);

namespace App\Service\Pipeline;

use App\Entity\ContentBrief;
use App\Entity\IntelligenceAnalysis;
use App\Entity\SerpAnalysis;
use App\Entity\TopicResearch;

final class BriefOutlineGeneratorService
{
    public function __construct(private readonly PipelineClaudeClient $claudeClient) {}

    public function generate(
        TopicResearch $topicResearch,
        SerpAnalysis $serpAnalysis,
        IntelligenceAnalysis $intelligenceAnalysis,
    ): ContentBrief {
        $result = $this->claudeClient->requestJson(
            $topicResearch,
            TopicResearch::STEP_BRIEF_OUTLINE,
            $this->systemPrompt(),
            [
                'topic' => [
                    'keyword' => $topicResearch->getPrimaryKeyword(),
                    'country' => $topicResearch->getCountry(),
                    'language' => $topicResearch->getLanguage(),
                    'sector' => $topicResearch->getSector(),
                    'audience' => $topicResearch->getAudience(),
                    'business_objective' => $topicResearch->getBusinessObjective(),
                ],
                'serp' => [
                    'competitors' => $serpAnalysis->getCompetitors(),
                    'questions' => $serpAnalysis->getQuestions(),
                    'content_gaps' => $serpAnalysis->getContentGaps(),
                    'serp_features' => $serpAnalysis->getSerpFeatures(),
                    'average_word_count' => $serpAnalysis->getAverageWordCount(),
                ],
                'intelligence' => [
                    'primary_intent' => $intelligenceAnalysis->getPrimaryIntent(),
                    'intent_breakdown' => $intelligenceAnalysis->getIntentBreakdown(),
                    'entities' => $intelligenceAnalysis->getEntities(),
                    'semantic_concepts' => $intelligenceAnalysis->getSemanticConcepts(),
                ],
            ],
            16000,
            0.25,
        );

        $parsed = $result->parsedResponse;
        $brief = $topicResearch->getContentBrief() ?? new ContentBrief();
        $brief
            ->setTopicResearch($topicResearch)
            ->setTargetAudience($this->nullableString($parsed['target_audience'] ?? null))
            ->setObjective($this->nullableString($parsed['objective'] ?? null))
            ->setIntent($this->nullableString($parsed['intent'] ?? $intelligenceAnalysis->getPrimaryIntent()))
            ->setToneRecommendation($this->nullableString($parsed['tone_recommendation'] ?? null))
            ->setTargetWordCount($this->nullableInt($parsed['target_word_count'] ?? null))
            ->setKeyEntities($this->listOfObjects($parsed['key_entities'] ?? []))
            ->setKeyQuestions($this->listOfObjects($parsed['key_questions'] ?? []))
            ->setCompetitorInsights($this->objectOrList($parsed['competitor_insights'] ?? []))
            ->setCta($this->nullableString($parsed['cta'] ?? null))
            ->setSources($this->listOfObjects($parsed['sources'] ?? []))
            ->setSeoTargets($this->object($parsed['seo_targets'] ?? []))
            ->setOutline($this->listOfObjects($parsed['outline'] ?? []))
            ->setFaqSuggestions($this->listOfObjects($parsed['faq_suggestions'] ?? []))
            ->setTableSuggestions($this->listOfObjects($parsed['table_suggestions'] ?? []))
            ->setEstimatedWordCount($this->nullableInt($parsed['estimated_word_count'] ?? $parsed['target_word_count'] ?? null))
            ->setGeneratedAt(new \DateTimeImmutable());

        return $brief;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are a content strategist and outline architect for a 5-step asynchronous article pipeline.
Create one complete brief and one structured outline from the provided SERP, questions, intent, entities, and semantic concepts.
Do not write the article. Do not invent citations or unverifiable facts.
Return only one valid JSON object with this schema:
{
  "target_audience": "string",
  "objective": "string",
  "intent": "string",
  "tone_recommendation": "string",
  "target_word_count": 0,
  "key_entities": [{"entity": "string", "importance": "high|medium|low", "context": "string"}],
  "key_questions": [{"question": "string", "priority": 0}],
  "competitor_insights": [{"topic": "string", "insight": "string", "gap": "string"}],
  "cta": "string",
  "sources": [{"claim": "string", "source_type": "official|research|statistics|documentation"}],
  "seo_targets": {"primary_keyword": "string", "secondary_keywords": ["string"], "lsi_terms": ["string"]},
  "outline": [{"level": "h2|h3", "title": "string", "key_points": ["string"], "questions_answered": ["string"], "entities_covered": ["string"]}],
  "faq_suggestions": [{"question": "string", "answer_angle": "string"}],
  "table_suggestions": [{"title": "string", "columns": ["string"], "purpose": "string"}],
  "estimated_word_count": 0
}
The outline must be ordered, complete, and suitable for a long-form SEO article without using an H1.
PROMPT;
    }

    private function nullableString(mixed $value): ?string
    {
        if (!is_scalar($value) || '' === trim((string) $value)) {
            return null;
        }

        return trim((string) $value);
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? max(0, (int) $value) : null;
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

    /** @return array<string, mixed>|array<int, array<string, mixed>> */
    private function objectOrList(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
