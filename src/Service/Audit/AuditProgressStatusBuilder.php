<?php

declare(strict_types=1);

namespace App\Service\Audit;

use App\Entity\Audit;
use App\Enum\AuditStatus;

final class AuditProgressStatusBuilder
{
    private const DEFAULT_AI_MAX_DURATION_SECONDS = 480;
    private const MAX_AI_ATTEMPTS = 2;

    /** @return array<string, mixed> */
    public function build(Audit $audit, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();
        $metadata = $audit->getMetadata() ?? [];
        $ai = is_array($metadata['ai_analysis'] ?? null) ? $metadata['ai_analysis'] : [];
        $aiStatus = is_scalar($ai['status'] ?? null) ? strtolower((string) $ai['status']) : 'pending';
        $phase = $this->phase($audit->getStatus(), $aiStatus);
        $stepStartedAt = $this->startedAt($audit, $ai, $phase);
        $elapsedSeconds = max(0, $now->getTimestamp() - $stepStartedAt->getTimestamp());
        $maxDuration = $this->positiveInt($ai['max_duration_seconds'] ?? null)
            ?? self::DEFAULT_AI_MAX_DURATION_SECONDS;
        $maximumAiWait = $maxDuration * self::MAX_AI_ATTEMPTS;

        $crawlStartedAt = $audit->getCrawlStartedAt() ?? $audit->getCreatedAt();
        $aiStartedAt = $this->resolveAiStartedAt($ai);

        return [
            'audit_status' => strtolower($audit->getStatus()->value),
            'ai_status' => $aiStatus,
            'phase' => $phase,
            'terminal' => $this->isTerminal($audit->getStatus(), $aiStatus),
            'successful' => AuditStatus::COMPLETED === $audit->getStatus() && 'completed' === $aiStatus,
            'title' => $this->title($phase),
            'message' => $this->message($phase, $audit),
            'estimate' => $this->estimate($phase, $maximumAiWait),
            'elapsed_seconds' => $elapsedSeconds,
            'step_started_at' => $stepStartedAt->format(\DateTimeInterface::ATOM),
            'crawl_started_at' => $crawlStartedAt->format(\DateTimeInterface::ATOM),
            'ai_started_at' => $aiStartedAt?->format(\DateTimeInterface::ATOM),
            'pages_crawled' => $audit->getPagesCrawled() ?? 0,
            'pages_failed' => $audit->getPagesFailed() ?? 0,
            'max_pages' => $audit->getMaxPages(),
            'seo_score' => $audit->getSeoScore(),
        ];
    }

    private function phase(AuditStatus $auditStatus, string $aiStatus): string
    {
        if (AuditStatus::FAILED === $auditStatus) {
            return 'failed';
        }

        if (AuditStatus::QUEUED === $auditStatus) {
            return 'crawl_queued';
        }

        if (AuditStatus::RUNNING === $auditStatus) {
            return 'crawling';
        }

        return match ($aiStatus) {
            'queued', 'pending' => 'ai_queued',
            'running' => 'analyzing',
            'completed' => 'completed',
            'failed' => 'failed',
            'not_configured' => 'not_configured',
            default => 'completed',
        };
    }

    /** @param array<string, mixed> $ai */
    private function startedAt(Audit $audit, array $ai, string $phase): \DateTimeImmutable
    {
        if (in_array($phase, ['ai_queued', 'analyzing'], true)) {
            $aiStart = $this->resolveAiStartedAt($ai);
            if (null !== $aiStart) {
                return $aiStart;
            }
        }

        return $audit->getCrawlStartedAt() ?? $audit->getCreatedAt();
    }

    /** @param array<string, mixed> $ai */
    private function resolveAiStartedAt(array $ai): ?\DateTimeImmutable
    {
        foreach (['started_at', 'queued_at'] as $key) {
            if (is_string($ai[$key] ?? null)) {
                try {
                    return new \DateTimeImmutable($ai[$key]);
                } catch (\Exception) {
                }
            }
        }

        return null;
    }

    private function isTerminal(AuditStatus $auditStatus, string $aiStatus): bool
    {
        if (AuditStatus::FAILED === $auditStatus) {
            return true;
        }

        return AuditStatus::COMPLETED === $auditStatus
            && in_array($aiStatus, ['completed', 'failed', 'not_configured'], true);
    }

    private function title(string $phase): string
    {
        return match ($phase) {
            'crawl_queued' => 'Audit queued',
            'crawling' => 'Crawling the website',
            'ai_queued' => 'SeoGeo analysis queued',
            'analyzing' => 'SeoGeo analyzing the crawl',
            'completed' => 'Analysis complete',
            'not_configured' => 'SeoGeo AI is not configured',
            default => 'Analysis stopped',
        };
    }

    private function message(string $phase, Audit $audit): string
    {
        return match ($phase) {
            'crawl_queued' => 'The background worker is preparing the website crawl.',
            'crawling' => sprintf(
                'The crawler is collecting real SEO facts from up to %s pages.',
                null === $audit->getMaxPages() ? 'the configured number of' : (string) $audit->getMaxPages(),
            ),
            'ai_queued' => 'The crawl is complete and SeoGeo is waiting for the background worker.',
            'analyzing' => sprintf(
                'SeoGeo is producing the SEO, content, and AI recommendation audit from %d crawled pages.',
                $audit->getPagesCrawled() ?? 0,
            ),
            'completed' => 'The complete audit is ready.',
            'not_configured' => 'The crawl completed, but the SeoGeo AI is unavailable.',
            default => 'The audit could not be completed. The report will show the recorded error.',
        };
    }

    private function estimate(string $phase, int $maximumAiWait): string
    {
        return match ($phase) {
            'crawl_queued' => 'The worker normally starts within a few seconds.',
            'crawling' => 'Crawl time depends on the website response speed. Claude starts immediately afterward.',
            'ai_queued' => 'Claude normally starts within a few seconds.',
            'analyzing' => sprintf(
                'Most 30-page analyses finish in 1-4 minutes. Complex retries stop after about %d minutes.',
                (int) ceil($maximumAiWait / 60),
            ),
            'completed' => 'Loading the finished report.',
            default => 'No additional waiting is required.',
        };
    }

    private function positiveInt(mixed $value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        $value = (int) $value;

        return $value > 0 ? $value : null;
    }
}
