<?php

declare(strict_types=1);

namespace App\Service\Cost;

use App\Entity\Project;
use App\Enum\PipelineQualityMode;
use App\Repository\ApiUsageLogRepository;
use App\Repository\ProjectApiBudgetRepository;
use Psr\Log\LoggerInterface;

final class ApiCostGuard
{
    public function __construct(
        private readonly ProjectApiBudgetRepository $budgetRepository,
        private readonly ApiUsageLogRepository $usageLogRepository,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param array{
     *     existing_data_available?: bool,
     *     fresh?: bool,
     *     quality_score?: int|float,
     *     min_quality?: int|float,
     *     estimated_cost?: int|float,
     *     estimated_tokens?: int,
     *     operation_type?: string,
     *     priority?: string,
     *     mode?: PipelineQualityMode|string|null
     * } $context
     */
    public function shouldCallExternalApi(Project $project, string $operation, array $context = []): bool
    {
        $mode = $this->mode($context['mode'] ?? null);
        $existingDataAvailable = true === ($context['existing_data_available'] ?? false);
        $fresh = true === ($context['fresh'] ?? false);
        $qualityScore = (float) ($context['quality_score'] ?? 0);
        $minQuality = (float) ($context['min_quality'] ?? 70);

        $economyThreshold = max(50.0, $minQuality - 20.0);
        if (PipelineQualityMode::ECONOMY === $mode && $existingDataAvailable && $qualityScore >= $economyThreshold) {
            return false;
        }

        if ($existingDataAvailable && $fresh && $qualityScore >= $minQuality && PipelineQualityMode::QUALITY !== $mode) {
            $this->logger->info('External API skipped because reusable data is sufficient.', [
                'project_id' => $project->getId(),
                'operation' => $operation,
                'mode' => $mode->value,
            ]);

            return false;
        }

        $budget = $this->budgetRepository->findForProject($project);
        if (null === $budget) {
            return true;
        }

        $now = new \DateTimeImmutable();
        $startOfDay = $now->setTime(0, 0);
        $startOfMonth = $now->modify('first day of this month')->setTime(0, 0);
        $dailyCost = $this->usageLogRepository->sumCostSince($project, $startOfDay);
        $monthlyCost = $this->usageLogRepository->sumCostSince($project, $startOfMonth);
        $estimatedCost = (float) ($context['estimated_cost'] ?? 0.0);

        if ($dailyCost + $estimatedCost > (float) $budget->getDailyMaxCost()) {
            $this->logger->warning('External API blocked by daily project budget.', [
                'project_id' => $project->getId(),
                'operation' => $operation,
            ]);

            return false;
        }

        if ($monthlyCost + $estimatedCost > (float) $budget->getMonthlyMaxCost()) {
            $this->logger->warning('External API blocked by monthly project budget.', [
                'project_id' => $project->getId(),
                'operation' => $operation,
            ]);

            return false;
        }

        $operationType = (string) ($context['operation_type'] ?? '');
        if ('ai' === $operationType) {
            $dailyTokens = $this->usageLogRepository->sumAiTokensSince($project, $startOfDay);
            $estimatedTokens = (int) ($context['estimated_tokens'] ?? 0);
            if ($dailyTokens + $estimatedTokens > $budget->getDailyMaxAiTokens()) {
                return false;
            }
        }

        if ('serp' === $operationType) {
            $dailySerpCalls = $this->usageLogRepository->countSerpCallsSince($project, $startOfDay);
            if ($dailySerpCalls + 1 > $budget->getDailyMaxSerpCalls()) {
                return false;
            }
        }

        return true;
    }

    private function mode(mixed $value): PipelineQualityMode
    {
        if ($value instanceof PipelineQualityMode) {
            return $value;
        }

        if (is_scalar($value)) {
            return PipelineQualityMode::tryFrom((string) $value) ?? PipelineQualityMode::BALANCED;
        }

        return PipelineQualityMode::BALANCED;
    }
}
