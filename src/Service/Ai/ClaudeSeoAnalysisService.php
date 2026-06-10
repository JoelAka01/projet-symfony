<?php

declare(strict_types=1);

namespace App\Service\Ai;

use App\Entity\Audit;
use App\Service\Audit\AuditInsightsBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ClaudeSeoAnalysisService
{
    private const DEFAULT_MODEL = 'claude-haiku-4-5-20251001';
    private const DEFAULT_BASE_URL = 'https://api.anthropic.com';
    private const ANTHROPIC_VERSION = '2023-06-01';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly AuditInsightsBuilder $insightsBuilder,
        private readonly ClaudeSeoAnalysisResponseParser $responseParser,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function analyze(Audit $audit): void
    {
        $apiKey = $this->envString('CLAUDE_API_KEY');
        $model = $this->envString('CLAUDE_MODEL') ?? self::DEFAULT_MODEL;
        $baseUrl = rtrim($this->envString('CLAUDE_API_BASE_URL') ?? self::DEFAULT_BASE_URL, '/');

        if (null === $apiKey) {
            $this->storeAiMetadata($audit, [
                'status' => 'not_configured',
                'provider' => 'anthropic',
                'model' => $model,
                'error' => 'CLAUDE_API_KEY is not configured. Real AI analysis cannot run.',
                'recommendations' => [],
                'analyzed_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ]);

            return;
        }

        $this->storeAiMetadata($audit, [
            'status' => 'running',
            'provider' => 'anthropic',
            'model' => $model,
            'recommendations' => [],
            'started_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);

        $crawlSummary = $this->insightsBuilder->buildClaudePayload($audit);

        try {
            $response = $this->httpClient->request('POST', $baseUrl.'/v1/messages', [
                'headers' => [
                    'x-api-key' => $apiKey,
                    'anthropic-version' => self::ANTHROPIC_VERSION,
                    'content-type' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'max_tokens' => 3000,
                    'temperature' => 0.2,
                    'system' => $this->systemPrompt(),
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => "Analyze this real website crawl and return only valid JSON matching the requested schema:\n\n".json_encode($crawlSummary, JSON_THROW_ON_ERROR),
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getContent(false);
            if ($statusCode >= 400) {
                throw new \RuntimeException(sprintf('Claude API returned HTTP %d: %s', $statusCode, $this->limit($body, 1000)));
            }

            $responseData = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($responseData)) {
                throw new \UnexpectedValueException('Claude API response was not a JSON object.');
            }

            $responseText = $this->extractTextContent($responseData);
            $parsed = $this->responseParser->parse($responseText);

            $this->storeAiMetadata($audit, $parsed + [
                'status' => 'completed',
                'provider' => 'anthropic',
                'model' => $model,
                'analyzed_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                'raw_response' => $this->limit($responseText, 15000),
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('Claude SEO analysis failed.', [
                'audit_id' => $audit->getId(),
                'exception' => $exception,
            ]);

            $this->storeAiMetadata($audit, [
                'status' => 'failed',
                'provider' => 'anthropic',
                'model' => $model,
                'error' => $this->limit($exception->getMessage(), 2000),
                'recommendations' => [],
                'analyzed_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ]);
        }
    }

    /** @param array<string, mixed> $metadata */
    private function storeAiMetadata(Audit $audit, array $metadata): void
    {
        $auditMetadata = $audit->getMetadata() ?? [];
        $auditMetadata['ai_analysis'] = $metadata;
        $audit->setMetadata($auditMetadata);

        $this->entityManager->flush();
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are a senior SEO, GEO, and technical content auditor.
You receive compact crawler facts from a real Symfony crawler. Treat those facts as the only source for objective crawl data.
Do not invent pages, HTTP statuses, metadata, headings, link counts, performance metrics, issue counts, or crawl errors.
Provide critical but practical SEO feedback. Separate objective evidence from interpretation.
Return only valid JSON with this schema:
{
  "global_score": 0-100,
  "technical_score": 0-100,
  "content_score": 0-100,
  "geo_score": 0-100,
  "confidence": 0.0-1.0,
  "summary": "string",
  "search_intent": "string",
  "target_audience": "string",
  "strengths": ["string"],
  "weaknesses": ["string"],
  "recommendations": [
    {
      "priority": "critical|high|medium|low",
      "category": "metadata|content|technical|links|images|indexability|performance|geo",
      "title": "string",
      "problem": "string",
      "evidence": "string from crawler facts",
      "why_it_matters": "string",
      "action": "string",
      "expected_impact": "string",
      "effort": "low|medium|high"
    }
  ],
  "suggested_title": "string|null",
  "suggested_meta_description": "string|null",
  "faq_suggestions": [{"question": "string", "answer": "string"}],
  "entities": ["string"],
  "citation_potential": "low|medium|high",
  "content_opportunities": ["string"],
  "technical_risks": ["string"],
  "short_answer_blocks": [{"question": "string", "answer": "string"}]
}
PROMPT;
    }

    /** @param array<string, mixed> $responseData */
    private function extractTextContent(array $responseData): string
    {
        $content = $responseData['content'] ?? null;
        if (!is_array($content)) {
            throw new \UnexpectedValueException('Claude response did not include content blocks.');
        }

        $text = '';
        foreach ($content as $block) {
            if (!is_array($block)) {
                continue;
            }

            if (($block['type'] ?? null) === 'text' && is_scalar($block['text'] ?? null)) {
                $text .= (string) $block['text'];
            }
        }

        $text = trim($text);
        if ('' === $text) {
            throw new \UnexpectedValueException('Claude response did not include text content.');
        }

        return $text;
    }

    private function envString(string $name): ?string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? false;
        if (false === $value) {
            $value = getenv($name);
        }

        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return '' === $value ? null : $value;
    }

    private function limit(string $value, int $maxLength): string
    {
        return strlen($value) > $maxLength ? substr($value, 0, $maxLength) : $value;
    }
}
