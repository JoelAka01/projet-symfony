<?php

declare(strict_types=1);

namespace App\Service\Cost;

use App\Entity\Project;
use App\Entity\TopicResearch;
use App\Entity\User;
use App\Enum\PipelineQualityMode;
use App\Service\Pipeline\ArticleGenerationPipelineService;

final class CostAwarePipelineService
{
    public function __construct(
        private readonly ArticleGenerationPipelineService $pipelineService,
        private readonly ApiCostGuard $apiCostGuard,
    ) {}

    /**
     * @param array{
     *     country?: string|null,
     *     language?: string|null,
     *     sector?: string|null,
     *     audience?: string|null,
     *     businessObjective?: string|null,
     *     qualityMode?: PipelineQualityMode|string|null,
     *     targetWordCount?: int|string|null
     * } $options
     */
    public function start(Project $project, User $user, string $keyword, array $options = []): TopicResearch
    {
        $mode = $this->mode($options['qualityMode'] ?? null);
        if (PipelineQualityMode::ECONOMY !== $mode && !$this->apiCostGuard->shouldCallExternalApi($project, 'pipeline_budget_probe', [
            'mode' => $mode,
            'operation_type' => 'ai',
            'estimated_cost' => 0.0,
            'estimated_tokens' => 0,
        ])) {
            $mode = PipelineQualityMode::ECONOMY;
        }

        $options['qualityMode'] = $mode;

        return $this->pipelineService->start($project, $user, $keyword, $options);
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
