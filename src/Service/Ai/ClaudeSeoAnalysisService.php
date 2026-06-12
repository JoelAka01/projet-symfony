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
    private const DEFAULT_MAX_TOKENS = 32000;
    private const MAX_OUTPUT_TOKENS = 64000;
    private const DEFAULT_TIMEOUT_SECONDS = 300;
    private const DEFAULT_MAX_DURATION_SECONDS = 480;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly AuditInsightsBuilder $insightsBuilder,
        private readonly ClaudeSeoAnalysisResponseParser $responseParser,
        private readonly ClaudeSeoAnalysisSchema $responseSchema,
        private readonly LoggerInterface $logger,
    ) {}

    public function analyze(Audit $audit): void
    {
        $apiKey = $this->envString('CLAUDE_API_KEY');
        $model = $this->envString('CLAUDE_MODEL') ?? self::DEFAULT_MODEL;
        $baseUrl = rtrim($this->envString('CLAUDE_API_BASE_URL') ?? self::DEFAULT_BASE_URL, '/');
        $maxTokens = $this->envInt('CLAUDE_MAX_TOKENS', self::DEFAULT_MAX_TOKENS, 1500, self::MAX_OUTPUT_TOKENS);
        $timeoutSeconds = $this->envInt('CLAUDE_TIMEOUT_SECONDS', self::DEFAULT_TIMEOUT_SECONDS, 30, 600);
        $maxDurationSeconds = $this->envInt(
            'CLAUDE_MAX_DURATION_SECONDS',
            max(self::DEFAULT_MAX_DURATION_SECONDS, $timeoutSeconds + 60),
            $timeoutSeconds,
            900,
        );

        if (null === $apiKey) {
            $this->storeAiMetadata($audit, [
                'status' => 'not_configured',
                'provider' => 'anthropic',
                'model' => $model,
                'max_tokens' => $maxTokens,
                'timeout_seconds' => $timeoutSeconds,
                'max_duration_seconds' => $maxDurationSeconds,
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
            'max_tokens' => $maxTokens,
            'timeout_seconds' => $timeoutSeconds,
            'max_duration_seconds' => $maxDurationSeconds,
            'recommendations' => [],
            'started_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);

        $crawlSummary = $this->insightsBuilder->buildClaudePayload($audit);

        try {
            $tokenBudgets = $maxTokens < self::MAX_OUTPUT_TOKENS
                ? [$maxTokens, self::MAX_OUTPUT_TOKENS]
                : [$maxTokens, $maxTokens];
            $parsed = null;
            $responseText = '';
            $responseUsage = [];
            $usedMaxTokens = $maxTokens;
            $attempts = 0;

            foreach ($tokenBudgets as $attempt => $tokenBudget) {
                ++$attempts;
                $response = $this->httpClient->request('POST', $baseUrl . '/v1/messages', [
                    'headers' => [
                        'x-api-key' => $apiKey,
                        'anthropic-version' => self::ANTHROPIC_VERSION,
                        'content-type' => 'application/json',
                    ],
                    'json' => [
                        'model' => $model,
                        'max_tokens' => $tokenBudget,
                        'temperature' => 0.2,
                        'system' => $this->structuredSystemPrompt(),
                        'messages' => [
                            [
                                'role' => 'user',
                                'content' => [
                                    [
                                        'type' => 'text',
                                        'text' => "Analyze this real website crawl. Use only the supplied crawler facts for objective claims:\n\n" . json_encode($crawlSummary, JSON_THROW_ON_ERROR),
                                    ],
                                ],
                            ],
                        ],
                        'output_config' => [
                            'format' => [
                                'type' => 'json_schema',
                                'schema' => $this->responseSchema->build(),
                            ],
                        ],
                    ],
                    'timeout' => $timeoutSeconds,
                    'max_duration' => $maxDurationSeconds,
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

                $stopReason = is_scalar($responseData['stop_reason'] ?? null) ? (string) $responseData['stop_reason'] : null;
                if ('refusal' === $stopReason) {
                    throw new \UnexpectedValueException('Claude refused to generate the requested SEO analysis.');
                }

                if ('max_tokens' === $stopReason) {
                    if (array_key_exists($attempt + 1, $tokenBudgets)) {
                        $this->logger->warning('Claude SEO analysis reached its output limit; retrying with the maximum token budget.', [
                            'audit_id' => $audit->getId(),
                            'max_tokens' => $tokenBudget,
                        ]);

                        continue;
                    }

                    throw new \UnexpectedValueException(sprintf('Claude response reached the maximum supported output limit (%d tokens).', $tokenBudget));
                }

                $responseText = $this->extractTextContent($responseData);

                try {
                    $parsed = $this->responseParser->parse($responseText);
                } catch (\UnexpectedValueException $exception) {
                    if (array_key_exists($attempt + 1, $tokenBudgets)) {
                        $this->logger->warning('Claude returned an invalid structured response; retrying once.', [
                            'audit_id' => $audit->getId(),
                            'exception' => $exception->getMessage(),
                        ]);

                        continue;
                    }

                    throw $exception;
                }

                $responseUsage = is_array($responseData['usage'] ?? null) ? $responseData['usage'] : [];
                $usedMaxTokens = $tokenBudget;
                break;
            }

            $this->storeAiMetadata($audit, $parsed + [
                'status' => 'completed',
                'provider' => 'anthropic',
                'model' => $model,
                'max_tokens' => $usedMaxTokens,
                'timeout_seconds' => $timeoutSeconds,
                'max_duration_seconds' => $maxDurationSeconds,
                'attempts' => $attempts,
                'usage' => $responseUsage,
                'analyzed_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                'raw_response' => $this->limit($responseText, 50000),
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
                'max_tokens' => $maxTokens,
                'timeout_seconds' => $timeoutSeconds,
                'max_duration_seconds' => $maxDurationSeconds,
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

    private function structuredSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a senior technical SEO, content strategy, and Generative Engine Optimization consultant.
Analyze the supplied real crawler facts and produce a complete, concise audit in the required structured format.

Rules:
- Never invent pages, HTTP statuses, metadata, headings, links, traffic, rankings, competitors, backlinks, model citations, or live AI mentions.
- Use crawler facts as the only source for objective statements. State when evidence is insufficient.
- Provide 6 to 10 prioritized recommendations with exact evidence, corrections, examples, expected impact, and effort.
- Keep most strings under 300 characters so the full report completes within the output budget.
- For ChatGPT, Gemini, and Perplexity, Claude is the only provider being called. Assess likely recommendation readiness from crawled content only.
- Never claim those products were queried or actually mentioned the business. Start each how_mentioned value with "Claude estimates".
- Set methodology_notice to explain that these are Claude-generated readiness estimates, not live checks of ChatGPT, Gemini, or Perplexity.
- Give model-specific content corrections for entity clarity, direct answers, citations, original evidence, structured data, FAQs, and comparison content.
- Empty strings or arrays are acceptable when crawler evidence is insufficient.
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

    private function envInt(string $name, int $default, int $min, int $max): int
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? false;
        if (false === $value) {
            $value = getenv($name);
        }

        if (!is_scalar($value) || '' === (string) $value) {
            return $default;
        }

        $integerValue = filter_var($value, FILTER_VALIDATE_INT);
        if (false === $integerValue) {
            return $default;
        }

        return max($min, min($max, $integerValue));
    }

    private function limit(string $value, int $maxLength): string
    {
        return strlen($value) > $maxLength ? substr($value, 0, $maxLength) : $value;
    }
}
