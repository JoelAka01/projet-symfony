<?php

declare(strict_types=1);

namespace App\Tests\Unit\Audit;

use App\Entity\Audit;
use App\Enum\AuditStatus;
use App\Service\Audit\AuditProgressStatusBuilder;
use PHPUnit\Framework\TestCase;

final class AuditProgressStatusBuilderTest extends TestCase
{
    public function testItReportsClaudeProgressWithElapsedTimeAndWaitGuidance(): void
    {
        $audit = (new Audit())
            ->setStatus(AuditStatus::COMPLETED)
            ->setPagesCrawled(30)
            ->setMaxPages(30)
            ->setMetadata([
                'ai_analysis' => [
                    'status' => 'running',
                    'started_at' => '2026-06-12T10:00:00+00:00',
                    'max_duration_seconds' => 480,
                ],
            ]);

        $status = (new AuditProgressStatusBuilder())->build(
            $audit,
            new \DateTimeImmutable('2026-06-12T10:02:05+00:00'),
        );

        self::assertSame('analyzing', $status['phase']);
        self::assertSame(125, $status['elapsed_seconds']);
        self::assertFalse($status['terminal']);
        self::assertStringContainsString('1-4 minutes', (string) $status['estimate']);
        self::assertSame(30, $status['pages_crawled']);
    }

    public function testItMarksCompletedClaudeAnalysisAsTerminal(): void
    {
        $audit = (new Audit())
            ->setStatus(AuditStatus::COMPLETED)
            ->setMetadata(['ai_analysis' => ['status' => 'completed']]);

        $status = (new AuditProgressStatusBuilder())->build($audit);

        self::assertSame('completed', $status['phase']);
        self::assertTrue($status['terminal']);
        self::assertTrue($status['successful']);
    }
}
