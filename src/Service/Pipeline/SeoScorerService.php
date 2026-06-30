<?php

declare(strict_types=1);

namespace App\Service\Pipeline;

use App\Entity\Article;
use App\Entity\ContentBrief;
use App\Entity\TopicResearch;
use App\Enum\ArticleStatus;

final class SeoScorerService
{
    public function __construct(private readonly PipelineClaudeClient $claudeClient) {}

    public function score(TopicResearch $topicResearch, Article $article, ContentBrief $contentBrief): int
    {
        $contentHtml = $article->getContentHtml();
        if (null === $contentHtml || '' === trim($contentHtml)) {
            throw new \RuntimeException('Article HTML is required before SEO scoring.');
        }

        $result = $this->claudeClient->requestJson(
            $topicResearch,
            TopicResearch::STEP_SEO_SCORE,
            $this->systemPrompt(),
            [
                'article' => [
                    'title' => $article->getTitle(),
                    'seo_title' => $article->getSeoTitle(),
                    'meta_description' => $article->getSeoDescription(),
                    'excerpt' => $article->getExcerpt(),
                    'content_html' => $contentHtml,
                    'faq' => $article->getFaqJson(),
                ],
                'targets' => [
                    'intent' => $contentBrief->getIntent(),
                    'seo_targets' => $contentBrief->getSeoTargets(),
                    'outline' => $contentBrief->getOutline(),
                    'key_questions' => $contentBrief->getKeyQuestions(),
                    'key_entities' => $contentBrief->getKeyEntities(),
                    'target_word_count' => $contentBrief->getTargetWordCount(),
                ],
            ],
            8000,
            0.1,
        );

        $parsed = $result->parsedResponse;
        $score = $this->scoreValue($parsed['score'] ?? null);
        $microCorrections = $this->object($parsed['micro_corrections'] ?? []);
        $seoTitle = $this->optionalString($microCorrections['seo_title'] ?? null, 70);
        $metaDescription = $this->optionalString($microCorrections['meta_description'] ?? null, 320);
        if (null !== $seoTitle) {
            $article->setSeoTitle($seoTitle);
        }
        if (null !== $metaDescription) {
            $article->setSeoDescription($metaDescription);
        }

        $metadata = $article->getGenerationMetadata() ?? [];
        $metadata['seo_review'] = [
            'score' => $score,
            'recommendations' => $this->listOfObjects($parsed['recommendations'] ?? []),
            'checks' => $this->object($parsed['checks'] ?? []),
            'micro_corrections' => $microCorrections,
            'reviewed_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];

        $article
            ->setSeoScore($score)
            ->setGenerationMetadata($metadata)
            ->setStatus(ArticleStatus::GENERATED);

        return $score;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are an SEO quality reviewer for the final step of a 5-step article pipeline.
Review the article HTML against the supplied brief targets. Do not rewrite the full article.
Return only one valid JSON object with this schema:
{
  "score": 0,
  "checks": {
    "keyword_usage": "string",
    "heading_structure": "string",
    "meta_lengths": "string",
    "faq_coverage": "string",
    "entity_coverage": "string",
    "question_coverage": "string",
    "readability": "string"
  },
  "recommendations": [{"priority": "high|medium|low", "message": "string"}],
  "micro_corrections": {"seo_title": "string|null", "meta_description": "string|null"}
}
Score from 0 to 100. Only provide micro-corrections for title or meta description when the existing values are missing, too long, too short, or unclear.
PROMPT;
    }

    private function scoreValue(mixed $value): int
    {
        if (!is_numeric($value)) {
            return 0;
        }

        return max(0, min(100, (int) round((float) $value)));
    }

    private function optionalString(mixed $value, int $maxLength): ?string
    {
        if (!is_scalar($value) || '' === trim((string) $value)) {
            return null;
        }

        return mb_substr(trim((string) $value), 0, $maxLength);
    }

    /** @return array<string, mixed> */
    private function object(mixed $value): array
    {
        return is_array($value) ? $value : [];
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
