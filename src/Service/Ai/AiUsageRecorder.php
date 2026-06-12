<?php

declare(strict_types=1);

namespace App\Service\Ai;

use App\Entity\AiUsage;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class AiUsageRecorder
{
    public const OPERATION_AUDIT_ANALYSIS = 'audit_analysis';
    public const OPERATION_ARTICLE_GENERATION = 'article_generation';

    public function __construct(private readonly EntityManagerInterface $entityManager) {}

    /**
     * @param array<string, mixed> $providerUsage
     */
    public function record(
        User $user,
        ?Project $project,
        string $provider,
        string $model,
        string $operation,
        array $providerUsage,
        ?string $resourceId = null,
    ): AiUsage {
        $inputTokens = $this->tokenValue($providerUsage, 'input_tokens');
        $outputTokens = $this->tokenValue($providerUsage, 'output_tokens');
        $cachedInputTokens = $this->tokenValue($providerUsage, 'cache_creation_input_tokens')
            + $this->tokenValue($providerUsage, 'cache_read_input_tokens');

        $usage = new AiUsage();
        $usage
            ->setUser($user)
            ->setProject($project)
            ->setProvider($provider)
            ->setModel($model)
            ->setOperation($operation)
            ->setInputTokens($inputTokens)
            ->setOutputTokens($outputTokens)
            ->setCachedInputTokens($cachedInputTokens)
            ->setCredits($inputTokens + $outputTokens + $cachedInputTokens)
            ->setResourceId($resourceId)
            ->setProviderUsage($providerUsage);

        $this->entityManager->persist($usage);

        return $usage;
    }

    /** @param array<string, mixed> $usage */
    private function tokenValue(array $usage, string $key): int
    {
        $value = $usage[$key] ?? 0;

        return is_numeric($value) ? max(0, (int) $value) : 0;
    }
}
