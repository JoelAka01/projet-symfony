<?php

declare(strict_types=1);

namespace App\Service\Ai;

use App\Entity\Audit;
use App\Service\Audit\AuditInsightsBuilder;
use App\Service\Audit\AuditProgressNotifier;
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
        private readonly AuditProgressNotifier $notifier,
        private readonly ?AiUsageRecorder $usageRecorder = null,
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
            $responseFormat = 'structured_json';

            foreach ($tokenBudgets as $attempt => $tokenBudget) {
                ++$attempts;
                $response = $this->requestClaude(
                    $baseUrl,
                    $apiKey,
                    $model,
                    $tokenBudget,
                    $timeoutSeconds,
                    $maxDurationSeconds,
                    $crawlSummary,
                    true,
                );

                $statusCode = $response->getStatusCode();
                $body = $response->getContent(false);
                if ($statusCode >= 400 && $this->isStructuredOutputCompatibilityError($body)) {
                    $this->logger->warning('Claude structured output was rejected; retrying with prompt-enforced JSON.', [
                        'audit_id' => $audit->getId(),
                        'status_code' => $statusCode,
                    ]);
                    $responseFormat = 'prompt_json';
                    $response = $this->requestClaude(
                        $baseUrl,
                        $apiKey,
                        $model,
                        $tokenBudget,
                        $timeoutSeconds,
                        $maxDurationSeconds,
                        $crawlSummary,
                        false,
                    );
                    $statusCode = $response->getStatusCode();
                    $body = $response->getContent(false);
                }

                if ($statusCode >= 400) {
                    throw new \RuntimeException(sprintf('Claude API returned HTTP %d: %s', $statusCode, $this->limit($body, 1000)));
                }

                $responseData = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($responseData)) {
                    throw new \UnexpectedValueException('Claude API response was not a JSON object.');
                }

                $responseUsage = $this->mergeUsage(
                    $responseUsage,
                    is_array($responseData['usage'] ?? null) ? $responseData['usage'] : [],
                );
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

                $usedMaxTokens = $tokenBudget;
                break;
            }

            $usageUser = $audit->getRequestedBy() ?? $audit->getProject()?->getOwner();
            if (null !== $usageUser && [] !== $responseUsage) {
                $this->usageRecorder?->record(
                    $usageUser,
                    $audit->getProject(),
                    'anthropic',
                    $model,
                    AiUsageRecorder::OPERATION_AUDIT_ANALYSIS,
                    $responseUsage,
                    $audit->getId(),
                );
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
                'response_format' => $responseFormat,
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

    public function reparseStoredResponse(Audit $audit): void
    {
        $aiAnalysis = $audit->getMetadata()['ai_analysis'] ?? null;
        if (!is_array($aiAnalysis) || !is_string($aiAnalysis['raw_response'] ?? null)) {
            throw new \RuntimeException('No stored Claude response is available for this audit.');
        }

        $parsed = $this->responseParser->parse($aiAnalysis['raw_response']);
        $operationalMetadata = array_intersect_key($aiAnalysis, array_flip([
            'provider',
            'model',
            'max_tokens',
            'timeout_seconds',
            'max_duration_seconds',
            'attempts',
            'usage',
            'response_format',
            'analyzed_at',
            'raw_response',
        ]));
        $operationalMetadata['status'] = 'completed';
        $operationalMetadata['reparsed_at'] = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);

        $this->storeAiMetadata($audit, $parsed + $operationalMetadata);
    }

    /** @param array<string, mixed> $metadata */
    private function storeAiMetadata(Audit $audit, array $metadata): void
    {
        $auditMetadata = $audit->getMetadata() ?? [];
        $auditMetadata['ai_analysis'] = $metadata;
        $audit->setMetadata($auditMetadata);

        $this->entityManager->flush();
        $this->notifier->notify($audit);
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
- Return 6 to 10 recommendations and complete every field in the compact response contract.
PROMPT;
    }

    /**
     * @param array<string, mixed> $crawlSummary
     */
    private function requestClaude(
        string $baseUrl,
        string $apiKey,
        string $model,
        int $maxTokens,
        int $timeoutSeconds,
        int $maxDurationSeconds,
        array $crawlSummary,
        bool $structuredOutput,
    ): \Symfony\Contracts\HttpClient\ResponseInterface {
        $systemPrompt = $this->structuredSystemPrompt();
        if (!$structuredOutput) {
            $systemPrompt .= <<<'PROMPT'

Return only one valid JSON object. Use these exact top-level keys:
scores, summary, score_rationale, audience, strengths, weaknesses, blockers, quick_wins,
keywords, technical, on_page, content_strategy, geo, recommendations, suggested_title,
suggested_meta_description, serp_opportunities, day_30, day_60, day_90.
The scores object must contain global, technical, content, onpage, geo, ux, confidence.
The geo.models value must be an array of exactly three objects for ChatGPT, Gemini, and Perplexity.
Each model object must contain model, status, assessment, sentiment, confidence, and evidence.
The geo object must also include concrete optimizations.
Do not use Markdown fences or add text outside the JSON object.
PROMPT;
        }

        $options = [
            'headers' => [
                'x-api-key' => $apiKey,
                'anthropic-version' => self::ANTHROPIC_VERSION,
                'content-type' => 'application/json',
            ],
            'json' => [
                'model' => $model,
                'max_tokens' => $maxTokens,
                'temperature' => 0.2,
                'system' => $systemPrompt,
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
            ],
            'timeout' => $timeoutSeconds,
            'max_duration' => $maxDurationSeconds,
        ];

        if ($structuredOutput) {
            $options['json']['output_config'] = [
                'format' => [
                    'type' => 'json_schema',
                    'schema' => $this->responseSchema->build(),
                ],
            ];
        }

        return $this->httpClient->request('POST', $baseUrl . '/v1/messages', $options);
    }

    private function isStructuredOutputCompatibilityError(string $body): bool
    {
        $body = strtolower($body);

        return str_contains($body, 'compiled grammar is too large')
            || str_contains($body, 'simplify your tool schemas')
            || str_contains($body, 'output_config')
            || str_contains($body, 'json_schema');
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

    /**
     * @param array<string, mixed> $total
     * @param array<string, mixed> $usage
     *
     * @return array<string, int>
     */
    private function mergeUsage(array $total, array $usage): array
    {
        foreach (['input_tokens', 'output_tokens', 'cache_creation_input_tokens', 'cache_read_input_tokens'] as $key) {
            $value = $usage[$key] ?? 0;
            $total[$key] = (int) ($total[$key] ?? 0) + (is_numeric($value) ? max(0, (int) $value) : 0);
        }

        return array_filter($total, static fn(int $value): bool => $value > 0);
    }
}
