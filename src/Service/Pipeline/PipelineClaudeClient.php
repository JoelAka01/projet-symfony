<?php

declare(strict_types=1);

namespace App\Service\Pipeline;

use App\Dto\PipelineClaudeResult;
use App\Entity\PipelineRunLog;
use App\Entity\TopicResearch;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PipelineClaudeClient
{
    private const DEFAULT_MODEL = 'claude-haiku-4-5-20251001';
    private const DEFAULT_BASE_URL = 'https://api.anthropic.com';
    private const ANTHROPIC_VERSION = '2023-06-01';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function requestJson(
        TopicResearch $topicResearch,
        string $step,
        string $systemPrompt,
        array $payload,
        int $maxTokens = 12000,
        float $temperature = 0.25,
    ): PipelineClaudeResult {
        $apiKey = $this->envString('CLAUDE_API_KEY');
        $model = $this->envString('CLAUDE_MODEL') ?? self::DEFAULT_MODEL;
        $baseUrl = rtrim($this->envString('CLAUDE_API_BASE_URL') ?? self::DEFAULT_BASE_URL, '/');
        $timeoutSeconds = $this->envInt('CLAUDE_TIMEOUT_SECONDS', 300, 30, 600);
        $maxDurationSeconds = $this->envInt('CLAUDE_MAX_DURATION_SECONDS', max(480, $timeoutSeconds + 60), $timeoutSeconds, 900);
        $promptPayload = json_encode($payload, JSON_THROW_ON_ERROR);
        $promptSent = "SYSTEM:\n" . $systemPrompt . "\n\nUSER:\n" . $promptPayload;
        $startedAt = microtime(true);

        $runLog = (new PipelineRunLog())
            ->setTopicResearch($topicResearch)
            ->setStep($step)
            ->setAttempt($this->nextAttempt($topicResearch, $step))
            ->setPromptSent($promptSent)
            ->setProvider('anthropic')
            ->setModel($model)
            ->setStatus(PipelineRunLog::STATUS_SUCCESS);
        $this->entityManager->persist($runLog);

        if (null === $apiKey) {
            $message = 'CLAUDE_API_KEY is not configured.';
            $runLog
                ->setDurationMs($this->durationMs($startedAt))
                ->setStatus(PipelineRunLog::STATUS_FAILED)
                ->setErrorMessage($message);

            throw new \RuntimeException($message);
        }

        try {
            $response = $this->httpClient->request('POST', $baseUrl . '/v1/messages', [
                'headers' => [
                    'x-api-key' => $apiKey,
                    'anthropic-version' => self::ANTHROPIC_VERSION,
                    'content-type' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'max_tokens' => $maxTokens,
                    'temperature' => $temperature,
                    'system' => $systemPrompt,
                    'messages' => [[
                        'role' => 'user',
                        'content' => [[
                            'type' => 'text',
                            'text' => $promptPayload,
                        ]],
                    ]],
                ],
                'timeout' => $timeoutSeconds,
                'max_duration' => $maxDurationSeconds,
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getContent(false);
            if ($statusCode >= 400) {
                throw new \RuntimeException(sprintf('Claude returned HTTP %d: %s', $statusCode, $this->limit(strip_tags($body), 1000)));
            }

            $responseData = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($responseData)) {
                throw new \UnexpectedValueException('Claude response was not a JSON object.');
            }

            $rawResponse = $this->extractTextContent($responseData);
            $runLog->setRawResponse($rawResponse);
            $parsedResponse = $this->parseJsonObject($rawResponse);
            $usage = is_array($responseData['usage'] ?? null) ? $responseData['usage'] : [];
            $inputTokens = $this->tokenValue($usage, 'input_tokens');
            $outputTokens = $this->tokenValue($usage, 'output_tokens');
            $cacheTokens = $this->tokenValue($usage, 'cache_creation_input_tokens') + $this->tokenValue($usage, 'cache_read_input_tokens');

            $runLog
                ->setParsedResponse($parsedResponse)
                ->setInputTokens($inputTokens)
                ->setOutputTokens($outputTokens)
                ->setTotalCredits($inputTokens + $outputTokens + $cacheTokens)
                ->setDurationMs($this->durationMs($startedAt))
                ->setStatus(PipelineRunLog::STATUS_SUCCESS);

            return new PipelineClaudeResult($parsedResponse, $rawResponse, $usage, $model);
        } catch (\Throwable $exception) {
            $runLog
                ->setDurationMs($this->durationMs($startedAt))
                ->setStatus(PipelineRunLog::STATUS_FAILED)
                ->setErrorMessage($exception->getMessage());

            $this->logger->error('Pipeline Claude call failed.', [
                'topic_research_id' => $topicResearch->getId(),
                'step' => $step,
                'exception' => $exception,
            ]);

            throw $exception;
        }
    }

    private function nextAttempt(TopicResearch $topicResearch, string $step): int
    {
        $attempt = 1;
        foreach ($topicResearch->getRunLogs() as $runLog) {
            if ($runLog->getStep() === $step) {
                $attempt = max($attempt, $runLog->getAttempt() + 1);
            }
        }

        return $attempt;
    }

    /** @param array<string, mixed> $responseData */
    private function extractTextContent(array $responseData): string
    {
        $text = '';
        $content = $responseData['content'] ?? [];
        if (!is_array($content)) {
            return $text;
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
            throw new \UnexpectedValueException('Claude response did not contain a JSON object.');
        }

        $decoded = json_decode(substr($text, $start, $end - $start + 1), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \UnexpectedValueException('Claude JSON response was not an object.');
        }

        return $decoded;
    }

    private function durationMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    /** @param array<string, mixed> $usage */
    private function tokenValue(array $usage, string $key): int
    {
        $value = $usage[$key] ?? 0;

        return is_numeric($value) ? max(0, (int) $value) : 0;
    }

    private function envString(string $name): ?string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);
        if (!is_scalar($value) || '' === trim((string) $value)) {
            return null;
        }

        return trim((string) $value);
    }

    private function envInt(string $name, int $default, int $min, int $max): int
    {
        $value = $this->envString($name);
        if (null === $value || !is_numeric($value)) {
            return $default;
        }

        return max($min, min($max, (int) $value));
    }

    private function limit(string $value, int $maxLength): string
    {
        return mb_substr($value, 0, $maxLength);
    }
}
