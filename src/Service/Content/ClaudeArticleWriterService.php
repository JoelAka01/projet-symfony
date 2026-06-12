<?php

declare(strict_types=1);

namespace App\Service\Content;

use App\Entity\Article;
use App\Entity\Keyword;
use App\Entity\User;
use App\Enum\ArticleStatus;
use App\Exception\CmsIntegrationException;
use App\Repository\AuditRepository;
use App\Service\Ai\AiUsageRecorder;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ClaudeArticleWriterService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly AuditRepository $auditRepository,
        private readonly ArticleHtmlSanitizer $htmlSanitizer,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly AiUsageRecorder $usageRecorder,
    ) {}

    public function generate(
        Article $article,
        User $requestedBy,
        string $brief,
        string $tone,
        int $targetWordCount,
        bool $includeFaq,
    ): void {
        $apiKey = $this->envString('CLAUDE_API_KEY');
        if (null === $apiKey) {
            throw new CmsIntegrationException('CLAUDE_API_KEY is not configured. Real article writing cannot run.');
        }

        $project = $article->getProject();
        if (null === $project) {
            throw new CmsIntegrationException('The article is not attached to a project.');
        }

        $model = $this->envString('CLAUDE_MODEL') ?? 'claude-haiku-4-5-20251001';
        $baseUrl = rtrim($this->envString('CLAUDE_API_BASE_URL') ?? 'https://api.anthropic.com', '/');
        $latestAudit = $this->auditRepository->findLatestCompletedForProject($project);
        $auditMetadata = $latestAudit?->getMetadata() ?? [];
        $analysis = is_array($auditMetadata['ai_analysis'] ?? null) ? $auditMetadata['ai_analysis'] : [];
        unset($analysis['raw_response']);

        $payload = [
            'project' => [
                'name' => $project->getName(),
                'language' => $project->getDefaultLanguage(),
                'country' => $project->getTargetCountry(),
            ],
            'article' => [
                'working_title' => $article->getTitle(),
                'seo_title' => $article->getSeoTitle(),
                'meta_description' => $article->getSeoDescription(),
                'primary_keyword' => $article->getPrimaryKeyword()?->getTerm(),
                'target_keywords' => array_map(
                    static fn(Keyword $keyword): string => $keyword->getTerm(),
                    $article->getTargetKeywords()->toArray(),
                ),
                'brief' => trim($brief),
                'tone' => $tone,
                'target_word_count' => max(500, min(4000, $targetWordCount)),
                'include_faq' => $includeFaq,
            ],
            'latest_real_audit_ai_analysis' => $analysis,
        ];

        try {
            $response = $this->httpClient->request('POST', $baseUrl . '/v1/messages', [
                'headers' => [
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'max_tokens' => 12000,
                    'temperature' => 0.35,
                    'system' => $this->systemPrompt(),
                    'messages' => [[
                        'role' => 'user',
                        'content' => [[
                            'type' => 'text',
                            'text' => json_encode($payload, JSON_THROW_ON_ERROR),
                        ]],
                    ]],
                ],
                'timeout' => 180,
                'max_duration' => 240,
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getContent(false);
            if ($statusCode >= 400) {
                throw new CmsIntegrationException(sprintf('Claude returned HTTP %d: %s', $statusCode, substr(strip_tags($body), 0, 1000)));
            }

            $responseData = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($responseData) || !is_array($responseData['content'] ?? null)) {
                throw new CmsIntegrationException('Claude returned an unexpected article response.');
            }

            $text = '';
            foreach ($responseData['content'] as $block) {
                if (is_array($block) && 'text' === ($block['type'] ?? null) && is_scalar($block['text'] ?? null)) {
                    $text .= (string) $block['text'];
                }
            }

            $generated = $this->parseGeneratedJson($text);
            $contentHtml = $this->htmlSanitizer->sanitize((string) $generated['content_html']);
            if ('' === trim($contentHtml)) {
                throw new CmsIntegrationException('Claude returned empty article HTML after sanitization.');
            }

            $article
                ->setTitle($this->requiredString($generated['title'] ?? null, 'title', 500))
                ->setSeoTitle($this->optionalString($generated['seo_title'] ?? null, 70))
                ->setSeoDescription($this->optionalString($generated['meta_description'] ?? null, 320))
                ->setExcerpt($this->optionalString($generated['excerpt'] ?? $generated['meta_description'] ?? null, 1000))
                ->setSlug($this->optionalString($generated['slug'] ?? null, 500))
                ->setContentHtml($contentHtml)
                ->setFaqJson(is_array($generated['faq'] ?? null) ? $generated['faq'] : null)
                ->setInternalLinksJson(is_array($generated['internal_link_suggestions'] ?? null) ? $generated['internal_link_suggestions'] : null)
                ->setExternalSourcesJson(is_array($generated['external_source_suggestions'] ?? null) ? $generated['external_source_suggestions'] : null)
                ->setGenerationMetadata([
                    'provider' => 'anthropic',
                    'model' => $model,
                    'audit_id' => $latestAudit?->getId(),
                    'image_suggestions' => is_array($generated['image_suggestions'] ?? null) ? $generated['image_suggestions'] : [],
                    'generated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                ])
                ->setGeneratedByProvider('anthropic')
                ->setGeneratedAt(new \DateTimeImmutable())
                ->setStatus(ArticleStatus::GENERATED)
                ->setWordCount(str_word_count(strip_tags($contentHtml)));

            $providerUsage = is_array($responseData['usage'] ?? null) ? $responseData['usage'] : [];
            if ([] !== $providerUsage) {
                $this->usageRecorder->record(
                    $requestedBy,
                    $project,
                    'anthropic',
                    $model,
                    AiUsageRecorder::OPERATION_ARTICLE_GENERATION,
                    $providerUsage,
                    $article->getId(),
                );
            }

            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            $this->logger->error('Claude article writing failed.', [
                'article_id' => $article->getId(),
                'exception' => $exception,
            ]);

            throw $exception;
        }
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You write a complete SEO and GEO-ready website article from a project brief, explicitly selected keywords, and real crawl-derived analysis.
Do not invent rankings, traffic, statistics, customer results, quotes, studies, or product capabilities. Mark claims needing a real source in external_source_suggestions.
Use the primary keyword naturally in the title, introduction, at least one H2 when appropriate, and conclusion. Use related keywords without stuffing.
Write useful, original, audience-appropriate content with direct answer blocks, logical H2/H3 sections, short paragraphs, lists or tables where useful, and an actionable conclusion.
Return only valid JSON. content_html may use: p, h2, h3, h4, ul, ol, li, strong, em, blockquote, a, table, thead, tbody, tr, th, td, code, pre, hr, br. Do not include scripts, styles, iframes, forms, an H1, or fake citations.
Return this schema:
{
  "title": "visible article title",
  "seo_title": "maximum 60 characters",
  "meta_description": "140-160 characters",
  "excerpt": "short summary",
  "slug": "lowercase-url-slug",
  "content_html": "complete semantic HTML article without H1",
  "faq": [{"question": "string", "answer": "string"}],
  "image_suggestions": [{"prompt": "specific production prompt", "alt_text": "descriptive SEO-safe alt text", "placement": "after section name"}],
  "internal_link_suggestions": [{"anchor": "string", "target_topic": "string"}],
  "external_source_suggestions": [{"claim": "string", "source_type": "official documentation|research|statistics"}]
}
PROMPT;
    }

    /** @return array<string, mixed> */
    private function parseGeneratedJson(string $text): array
    {
        $text = trim($text);
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if (false === $start || false === $end || $end < $start) {
            throw new CmsIntegrationException('Claude article response did not contain JSON.');
        }

        try {
            $decoded = json_decode(substr($text, $start, $end - $start + 1), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new CmsIntegrationException('Claude article JSON could not be parsed.', previous: $exception);
        }

        if (!is_array($decoded) || !is_scalar($decoded['content_html'] ?? null)) {
            throw new CmsIntegrationException('Claude article JSON did not include content_html.');
        }

        return $decoded;
    }

    private function requiredString(mixed $value, string $field, int $maxLength): string
    {
        $value = $this->optionalString($value, $maxLength);
        if (null === $value) {
            throw new CmsIntegrationException(sprintf('Claude article JSON did not include %s.', $field));
        }

        return $value;
    }

    private function optionalString(mixed $value, int $maxLength): ?string
    {
        if (!is_scalar($value) || '' === trim((string) $value)) {
            return null;
        }

        return substr(trim((string) $value), 0, $maxLength);
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
