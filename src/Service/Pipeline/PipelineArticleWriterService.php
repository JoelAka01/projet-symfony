<?php

declare(strict_types=1);

namespace App\Service\Pipeline;

use App\Entity\Article;
use App\Entity\ContentBrief;
use App\Entity\IntelligenceAnalysis;
use App\Entity\Keyword;
use App\Entity\SerpAnalysis;
use App\Entity\TopicResearch;
use App\Enum\ArticleStatus;
use App\Repository\KeywordRepository;
use App\Service\Ai\AiUsageRecorder;
use App\Service\Content\ArticleHtmlSanitizer;
use Doctrine\ORM\EntityManagerInterface;

final class PipelineArticleWriterService
{
    public function __construct(
        private readonly PipelineClaudeClient $claudeClient,
        private readonly ArticleHtmlSanitizer $htmlSanitizer,
        private readonly KeywordRepository $keywordRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly AiUsageRecorder $usageRecorder,
    ) {}

    public function write(
        TopicResearch $topicResearch,
        ContentBrief $contentBrief,
        IntelligenceAnalysis $intelligenceAnalysis,
        SerpAnalysis $serpAnalysis,
    ): Article {
        $result = $this->claudeClient->requestJson(
            $topicResearch,
            TopicResearch::STEP_ARTICLE,
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
                'brief' => [
                    'target_audience' => $contentBrief->getTargetAudience(),
                    'objective' => $contentBrief->getObjective(),
                    'intent' => $contentBrief->getIntent(),
                    'tone_recommendation' => $contentBrief->getToneRecommendation(),
                    'target_word_count' => $contentBrief->getTargetWordCount(),
                    'key_entities' => $contentBrief->getKeyEntities(),
                    'key_questions' => $contentBrief->getKeyQuestions(),
                    'seo_targets' => $contentBrief->getSeoTargets(),
                    'outline' => $contentBrief->getOutline(),
                    'faq_suggestions' => $contentBrief->getFaqSuggestions(),
                    'table_suggestions' => $contentBrief->getTableSuggestions(),
                    'sources' => $contentBrief->getSources(),
                    'cta' => $contentBrief->getCta(),
                ],
                'intelligence' => [
                    'entities' => $intelligenceAnalysis->getEntities(),
                    'semantic_concepts' => $intelligenceAnalysis->getSemanticConcepts(),
                ],
                'serp' => [
                    'questions' => $serpAnalysis->getQuestions(),
                    'content_gaps' => $serpAnalysis->getContentGaps(),
                ],
            ],
            16000,
            0.35,
        );

        $parsed = $result->parsedResponse;
        $contentHtml = $this->htmlSanitizer->sanitize($this->requiredString($parsed['content_html'] ?? null, 'content_html', 200000));
        if ('' === trim($contentHtml)) {
            throw new \UnexpectedValueException('Generated article HTML is empty after sanitization.');
        }

        $project = $topicResearch->getProject();
        if (null === $project) {
            throw new \RuntimeException('The topic research is not attached to a project.');
        }

        $article = $topicResearch->getArticle() ?? new Article();
        $article
            ->setProject($project)
            ->setTopicResearch($topicResearch)
            ->setTitle($this->requiredString($parsed['title'] ?? $topicResearch->getPrimaryKeyword(), 'title', 500))
            ->setSeoTitle($this->optionalString($parsed['seo_title'] ?? null, 70))
            ->setSeoDescription($this->optionalString($parsed['meta_description'] ?? null, 320))
            ->setExcerpt($this->optionalString($parsed['excerpt'] ?? $parsed['meta_description'] ?? null, 1000))
            ->setSlug($this->optionalString($parsed['slug'] ?? null, 500))
            ->setContentHtml($contentHtml)
            ->setFaqJson($this->listOfObjects($parsed['faq'] ?? []))
            ->setInternalLinksJson($this->listOfObjects($parsed['internal_link_suggestions'] ?? []))
            ->setExternalSourcesJson($this->listOfObjects($parsed['external_source_suggestions'] ?? []))
            ->setGenerationMetadata([
                'pipeline' => 'v2',
                'provider' => 'anthropic',
                'model' => $result->model,
                'topic_research_id' => $topicResearch->getId(),
                'image_suggestions' => $this->listOfObjects($parsed['image_suggestions'] ?? []),
                'entities' => $intelligenceAnalysis->getEntities(),
                'questions' => $serpAnalysis->getQuestions(),
                'generated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ])
            ->setGeneratedByProvider('anthropic')
            ->setGeneratedAt(new \DateTimeImmutable())
            ->setStatus(ArticleStatus::DRAFT)
            ->setWordCount(str_word_count(strip_tags($contentHtml)));

        $this->attachKeywords($article, $topicResearch, $contentBrief);
        $this->entityManager->persist($article);
        $topicResearch->addArticle($article);

        $requestedBy = $topicResearch->getRequestedBy();
        if (null !== $requestedBy && [] !== $result->usage) {
            $this->usageRecorder->record(
                $requestedBy,
                $project,
                'anthropic',
                $result->model,
                AiUsageRecorder::OPERATION_ARTICLE_GENERATION,
                $result->usage,
                $article->getId(),
            );
        }

        return $article;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are an expert SEO writer producing the article step of a 5-step asynchronous pipeline.
Write a complete, useful, CMS-ready article that follows the provided outline section by section.
Cover the priority questions, naturally integrate the entities and semantic concepts, and avoid unsupported statistics or invented citations.
Return only one valid JSON object with this schema:
{
  "title": "visible article title",
  "seo_title": "maximum 60 characters",
  "meta_description": "140-160 characters",
  "excerpt": "short summary",
  "slug": "lowercase-url-slug",
  "content_html": "complete semantic HTML article without H1",
  "faq": [{"question": "string", "answer": "string"}],
  "image_suggestions": [{"prompt": "string", "alt_text": "string", "placement": "string"}],
  "internal_link_suggestions": [{"anchor": "string", "target_topic": "string"}],
  "external_source_suggestions": [{"claim": "string", "source_type": "official documentation|research|statistics"}]
}
Allowed HTML tags in content_html: p, h2, h3, h4, ul, ol, li, strong, em, blockquote, a, table, thead, tbody, tr, th, td, code, pre, hr, br.
Do not include scripts, styles, iframes, forms, SVG, an H1, or Markdown fences.
PROMPT;
    }

    private function attachKeywords(Article $article, TopicResearch $topicResearch, ContentBrief $contentBrief): void
    {
        $project = $topicResearch->getProject();
        if (null === $project) {
            return;
        }

        $seoTargets = $contentBrief->getSeoTargets();
        $primaryTerm = $this->optionalString($seoTargets['primary_keyword'] ?? null, 500) ?? $topicResearch->getPrimaryKeyword();
        $primaryKeyword = $this->findOrCreateKeyword($project, $primaryTerm, $contentBrief->getIntent(), false);
        $article->setPrimaryKeyword($primaryKeyword);

        $terms = [$primaryTerm];
        foreach (['secondary_keywords', 'lsi_terms'] as $key) {
            $items = $seoTargets[$key] ?? [];
            if (!is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                $term = $this->optionalString($item, 500);
                if (null !== $term) {
                    $terms[] = $term;
                }
            }
        }

        foreach (array_slice(array_values(array_unique($terms)), 0, 20) as $term) {
            $article->addTargetKeyword($this->findOrCreateKeyword($project, $term, $contentBrief->getIntent(), $term !== $primaryTerm));
        }
    }

    private function findOrCreateKeyword(\App\Entity\Project $project, string $term, ?string $intent, bool $fanout): Keyword
    {
        $keyword = $this->keywordRepository->findOneBy([
            'project' => $project,
            'term' => $term,
        ]);
        if ($keyword instanceof Keyword) {
            return $keyword;
        }

        $keyword = new Keyword();
        $keyword
            ->setProject($project)
            ->setTerm($term)
            ->setIntent($intent)
            ->setSource('pipeline_v2')
            ->setIsFanoutKeyword($fanout);
        $this->entityManager->persist($keyword);

        return $keyword;
    }

    private function requiredString(mixed $value, string $field, int $maxLength): string
    {
        $string = $this->optionalString($value, $maxLength);
        if (null === $string) {
            throw new \UnexpectedValueException(sprintf('Article response did not include %s.', $field));
        }

        return $string;
    }

    private function optionalString(mixed $value, int $maxLength): ?string
    {
        if (!is_scalar($value) || '' === trim((string) $value)) {
            return null;
        }

        return mb_substr(trim((string) $value), 0, $maxLength);
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
