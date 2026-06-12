<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ai;

use App\Entity\Audit;
use App\Service\Ai\ClaudeSeoAnalysisResponseParser;
use App\Service\Ai\ClaudeSeoAnalysisSchema;
use App\Service\Ai\ClaudeSeoAnalysisService;
use App\Service\Audit\AuditInsightsBuilder;
use App\Service\Audit\AuditProgressNotifier;
use App\Service\Audit\AuditProgressStatusBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mercure\HubInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ClaudeSeoAnalysisServiceTest extends TestCase
{
    /** @var array<string, array{has_env: bool, env: string|null, has_server: bool, server: string|null, has_getenv: bool, getenv: string|null}> */
    private array $previousEnv = [];

    protected function tearDown(): void
    {
        foreach ($this->previousEnv as $name => $previous) {
            if ($previous['has_env']) {
                $_ENV[$name] = $previous['env'];
            } else {
                unset($_ENV[$name]);
            }

            if ($previous['has_server']) {
                $_SERVER[$name] = $previous['server'];
            } else {
                unset($_SERVER[$name]);
            }

            putenv($previous['has_getenv'] ? sprintf('%s=%s', $name, $previous['getenv']) : $name);
        }

        $this->previousEnv = [];

        parent::tearDown();
    }

    public function testItUsesConfiguredClaudeTimeoutsAndStoresReturnedScores(): void
    {
        $this->setEnv('CLAUDE_API_KEY', 'test-key');
        $this->setEnv('CLAUDE_MODEL', 'test-model');
        $this->setEnv('CLAUDE_API_BASE_URL', 'https://api.anthropic.com');
        $this->setEnv('CLAUDE_MAX_TOKENS', '1500');
        $this->setEnv('CLAUDE_TIMEOUT_SECONDS', '222');
        $this->setEnv('CLAUDE_MAX_DURATION_SECONDS', '333');

        $capturedOptions = null;
        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options) use (&$capturedOptions): MockResponse {
                $capturedOptions = $options;

                self::assertSame('POST', $method);
                self::assertSame('https://api.anthropic.com/v1/messages', $url);

                $analysisJson = json_encode([
                    'summary' => 'The crawl is usable and needs stronger GEO blocks.',
                    'global_score' => 81,
                    'technical_score' => 84,
                    'content_score' => 73,
                    'geo_score' => 68,
                    'recommendations' => [],
                ], JSON_THROW_ON_ERROR);

                return new MockResponse(json_encode([
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $analysisJson,
                        ],
                    ],
                    'stop_reason' => 'end_turn',
                ], JSON_THROW_ON_ERROR), ['http_code' => 200]);
            },
        );

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(2))->method('flush');

        $audit = new Audit();

        $notifier = new AuditProgressNotifier(
            $this->createMock(HubInterface::class),
            new AuditProgressStatusBuilder(),
            new NullLogger()
        );

        $service = new ClaudeSeoAnalysisService(
            $httpClient,
            $entityManager,
            new AuditInsightsBuilder(),
            new ClaudeSeoAnalysisResponseParser(),
            new ClaudeSeoAnalysisSchema(),
            new NullLogger(),
            $notifier,
        );

        $service->analyze($audit);

        self::assertIsArray($capturedOptions);
        self::assertSame(222.0, $capturedOptions['timeout']);
        self::assertSame(333.0, $capturedOptions['max_duration']);
        self::assertIsString($capturedOptions['body']);
        $requestPayload = json_decode($capturedOptions['body'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(1500, $requestPayload['max_tokens']);
        self::assertSame('test-model', $requestPayload['model']);
        self::assertSame('json_schema', $requestPayload['output_config']['format']['type']);
        self::assertSame('object', $requestPayload['output_config']['format']['schema']['type']);

        $metadata = $audit->getMetadata();
        self::assertIsArray($metadata);
        self::assertIsArray($metadata['ai_analysis']);
        self::assertSame('completed', $metadata['ai_analysis']['status']);
        self::assertSame(81, $metadata['ai_analysis']['global_score']);
        self::assertSame(73, $metadata['ai_analysis']['content_score']);
        self::assertSame(68, $metadata['ai_analysis']['geo_score']);
        self::assertSame(222, $metadata['ai_analysis']['timeout_seconds']);
        self::assertSame(333, $metadata['ai_analysis']['max_duration_seconds']);
    }

    public function testItRetriesWithMaximumOutputBudgetAfterClaudeTruncation(): void
    {
        $this->setEnv('CLAUDE_API_KEY', 'test-key');
        $this->setEnv('CLAUDE_MAX_TOKENS', '1500');

        $requestedTokenBudgets = [];
        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options) use (&$requestedTokenBudgets): MockResponse {
                self::assertSame('POST', $method);
                self::assertSame('https://api.anthropic.com/v1/messages', $url);

                $requestPayload = json_decode((string) $options['body'], true, 512, JSON_THROW_ON_ERROR);
                $requestedTokenBudgets[] = $requestPayload['max_tokens'];

                if (1 === count($requestedTokenBudgets)) {
                    return new MockResponse(json_encode([
                        'content' => [['type' => 'text', 'text' => '{"summary":"truncated"']],
                        'stop_reason' => 'max_tokens',
                    ], JSON_THROW_ON_ERROR), ['http_code' => 200]);
                }

                return new MockResponse(json_encode([
                    'content' => [[
                        'type' => 'text',
                        'text' => json_encode([
                            'summary' => 'Complete analysis after retry.',
                            'global_score' => 75,
                            'recommendations' => [],
                        ], JSON_THROW_ON_ERROR),
                    ]],
                    'stop_reason' => 'end_turn',
                    'usage' => [
                        'input_tokens' => 900,
                        'output_tokens' => 2400,
                    ],
                ], JSON_THROW_ON_ERROR), ['http_code' => 200]);
            },
        );

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(2))->method('flush');

        $audit = new Audit();
        $notifier = new AuditProgressNotifier(
            $this->createMock(HubInterface::class),
            new AuditProgressStatusBuilder(),
            new NullLogger()
        );

        $service = new ClaudeSeoAnalysisService(
            $httpClient,
            $entityManager,
            new AuditInsightsBuilder(),
            new ClaudeSeoAnalysisResponseParser(),
            new ClaudeSeoAnalysisSchema(),
            new NullLogger(),
            $notifier,
        );

        $service->analyze($audit);

        self::assertSame([1500, 64000], $requestedTokenBudgets);
        self::assertSame('completed', $audit->getMetadata()['ai_analysis']['status']);
        self::assertSame(64000, $audit->getMetadata()['ai_analysis']['max_tokens']);
        self::assertSame(2, $audit->getMetadata()['ai_analysis']['attempts']);
        self::assertSame(2400, $audit->getMetadata()['ai_analysis']['usage']['output_tokens']);
    }

    public function testItFallsBackToPromptJsonWhenAnthropicRejectsTheStructuredGrammar(): void
    {
        $this->setEnv('CLAUDE_API_KEY', 'test-key');
        $requests = [];

        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options) use (&$requests): MockResponse {
                $requestPayload = json_decode((string) $options['body'], true, 512, JSON_THROW_ON_ERROR);
                $requests[] = $requestPayload;

                if (1 === count($requests)) {
                    return new MockResponse(json_encode([
                        'type' => 'error',
                        'error' => [
                            'type' => 'invalid_request_error',
                            'message' => 'The compiled grammar is too large. Simplify your tool schemas.',
                        ],
                    ], JSON_THROW_ON_ERROR), ['http_code' => 400]);
                }

                return new MockResponse(json_encode([
                    'content' => [[
                        'type' => 'text',
                        'text' => json_encode([
                            'scores' => [
                                'global' => 78,
                                'technical' => 80,
                                'content' => 70,
                                'onpage' => 74,
                                'geo' => 65,
                                'ux' => 71,
                                'confidence' => 0.8,
                            ],
                            'summary' => 'Prompt JSON fallback completed the analysis.',
                            'recommendations' => [],
                        ], JSON_THROW_ON_ERROR),
                    ]],
                    'stop_reason' => 'end_turn',
                ], JSON_THROW_ON_ERROR), ['http_code' => 200]);
            },
        );

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(2))->method('flush');

        $audit = new Audit();
        $notifier = new AuditProgressNotifier(
            $this->createMock(HubInterface::class),
            new AuditProgressStatusBuilder(),
            new NullLogger()
        );

        $service = new ClaudeSeoAnalysisService(
            $httpClient,
            $entityManager,
            new AuditInsightsBuilder(),
            new ClaudeSeoAnalysisResponseParser(),
            new ClaudeSeoAnalysisSchema(),
            new NullLogger(),
            $notifier,
        );

        $service->analyze($audit);

        self::assertCount(2, $requests);
        self::assertArrayHasKey('output_config', $requests[0]);
        self::assertArrayNotHasKey('output_config', $requests[1]);
        self::assertSame('completed', $audit->getMetadata()['ai_analysis']['status']);
        self::assertSame('prompt_json', $audit->getMetadata()['ai_analysis']['response_format']);
        self::assertSame(78, $audit->getMetadata()['ai_analysis']['global_score']);
    }

    private function setEnv(string $name, string $value): void
    {
        if (!array_key_exists($name, $this->previousEnv)) {
            $getenv = getenv($name);
            $this->previousEnv[$name] = [
                'has_env' => array_key_exists($name, $_ENV),
                'env' => isset($_ENV[$name]) ? (string) $_ENV[$name] : null,
                'has_server' => array_key_exists($name, $_SERVER),
                'server' => isset($_SERVER[$name]) ? (string) $_SERVER[$name] : null,
                'has_getenv' => false !== $getenv,
                'getenv' => false === $getenv ? null : (string) $getenv,
            ];
        }

        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
        putenv(sprintf('%s=%s', $name, $value));
    }
}
