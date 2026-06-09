<?php

declare(strict_types=1);

namespace App\Service\Crawler;

use App\Entity\AuditIssue;

final class SeoScoreCalculator
{
    private const DEDUCTIONS = [
        'critical' => 20,
        'high' => 10,
        'medium' => 5,
        'low' => 2,
        'info' => 0,
    ];

    /**
     * Formula: start from 100 and subtract a fixed amount for each issue severity.
     * The final score is clamped between 0 and 100 to keep it display-friendly.
     *
     * @param iterable<AuditIssue> $issues
     */
    public function calculate(iterable $issues): int
    {
        $score = 100;

        foreach ($issues as $issue) {
            $severity = strtolower((string) $issue->getSeverity());
            $score -= self::DEDUCTIONS[$severity] ?? self::DEDUCTIONS['medium'];
        }

        return max(0, min(100, $score));
    }
}
