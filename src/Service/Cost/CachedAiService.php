<?php

declare(strict_types=1);

namespace App\Service\Cost;

use App\Entity\AiCache;
use App\Entity\Project;
use App\Repository\AiCacheRepository;
use Doctrine\ORM\EntityManagerInterface;

final class CachedAiService
{
    public function __construct(
        private readonly AiCacheRepository $aiCacheRepository,
        private readonly ApiUsageLogger $usageLogger,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /** @param array<string, mixed> $input */
    public function inputHash(array $input): string
    {
        ksort($input);

        return hash('sha256', json_encode($input, JSON_THROW_ON_ERROR));
    }

    public function find(string $operation, string $inputHash, string $model): ?AiCache
    {
        return $this->aiCacheRepository->findReusable($operation, $inputHash, $model);
    }

    /** @param array<string, mixed> $responseJson */
    public function save(string $operation, string $inputHash, string $model, array $responseJson, int $tokensSavedEstimate): AiCache
    {
        $cache = $this->aiCacheRepository->findReusable($operation, $inputHash, $model) ?? new AiCache();
        $cache
            ->setOperation($operation)
            ->setInputHash($inputHash)
            ->setModel($model)
            ->setResponseJson($responseJson)
            ->setTokensSavedEstimate($tokensSavedEstimate);

        $this->entityManager->persist($cache);

        return $cache;
    }

    public function logHit(Project $project, string $operation, AiCache $cache): void
    {
        $this->usageLogger->log(
            $project,
            'anthropic',
            $operation,
            cacheHit: true,
            savedCostEstimate: $this->estimatedAiCost($cache->getTokensSavedEstimate(), 0),
        );
    }

    public function estimatedAiCost(int $inputTokens, int $outputTokens): float
    {
        return (($inputTokens / 1000000) * 0.80) + (($outputTokens / 1000000) * 4.00);
    }
}
