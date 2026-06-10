<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ai;

use App\Entity\Audit;
use App\Service\Ai\ClaudeSeoAnalysisResponseParser;
use App\Service\Ai\ClaudeSeoAnalysisService;
use App\Service\Audit\AuditInsightsBuilder;
use Doctrine\ORM\EntityManagerInterface;
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

        $service = new ClaudeSeoAnalysisService(
            $httpClient,
            $entityManager,
            new AuditInsightsBuilder(),
            new ClaudeSeoAnalysisResponseParser(),
            new NullLogger(),
        );

        $service->analyze($audit);

        self::assertIsArray($capturedOptions);
        self::assertSame(222.0, $capturedOptions['timeout']);
        self::assertSame(333.0, $capturedOptions['max_duration']);
        self::assertIsString($capturedOptions['body']);
        $requestPayload = json_decode($capturedOptions['body'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(1500, $requestPayload['max_tokens']);
        self::assertSame('test-model', $requestPayload['model']);

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
