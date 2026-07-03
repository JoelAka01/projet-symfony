<?php

declare(strict_types=1);

namespace App\Service\Cost;

use App\Entity\ApiUsageLog;
use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;

final class ApiUsageLogger
{
    public function __construct(private readonly EntityManagerInterface $entityManager) {}

    public function log(
        ?Project $project,
        string $provider,
        string $operation,
        float $estimatedCost = 0.0,
        int $tokensInput = 0,
        int $tokensOutput = 0,
        bool $cacheHit = false,
        float $savedCostEstimate = 0.0,
    ): ApiUsageLog {
        $log = (new ApiUsageLog())
            ->setProject($project)
            ->setProvider($provider)
            ->setOperation($operation)
            ->setEstimatedCost(sprintf('%.6F', max(0.0, $estimatedCost)))
            ->setTokensInput($tokensInput)
            ->setTokensOutput($tokensOutput)
            ->setCacheHit($cacheHit)
            ->setSavedCostEstimate(sprintf('%.6F', max(0.0, $savedCostEstimate)));

        $this->entityManager->persist($log);

        return $log;
    }
}
