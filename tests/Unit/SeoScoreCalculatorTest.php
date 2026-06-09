<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\AuditIssue;
use App\Service\Crawler\SeoScoreCalculator;
use PHPUnit\Framework\TestCase;

final class SeoScoreCalculatorTest extends TestCase
{
    public function testScoreStartsAtOneHundredWhenThereAreNoIssues(): void
    {
        $calculator = new SeoScoreCalculator();

        self::assertSame(100, $calculator->calculate([]));
    }

    public function testScoreDeductsPointsBySeverity(): void
    {
        $calculator = new SeoScoreCalculator();

        $issues = [
            $this->issue('critical'),
            $this->issue('high'),
            $this->issue('medium'),
            $this->issue('low'),
            $this->issue('info'),
        ];

        self::assertSame(63, $calculator->calculate($issues));
    }

    public function testScoreNeverDropsBelowZero(): void
    {
        $calculator = new SeoScoreCalculator();
        $issues = [];

        for ($i = 0; $i < 10; ++$i) {
            $issues[] = $this->issue('critical');
        }

        self::assertSame(0, $calculator->calculate($issues));
    }

    private function issue(string $severity): AuditIssue
    {
        $issue = new AuditIssue();
        $issue->setSeverity($severity);

        return $issue;
    }
}
