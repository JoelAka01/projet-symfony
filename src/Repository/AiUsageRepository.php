<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AiUsage;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AiUsage> */
class AiUsageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiUsage::class);
    }

    /**
     * @return array{credits: int, inputTokens: int, outputTokens: int, cachedInputTokens: int, calls: int}
     */
    public function getGlobalSummary(): array
    {
        /** @var array<string, int|string|null> $row */
        $row = $this->createQueryBuilder('usage')
            ->select('COALESCE(SUM(usage.credits), 0) AS credits')
            ->addSelect('COALESCE(SUM(usage.inputTokens), 0) AS inputTokens')
            ->addSelect('COALESCE(SUM(usage.outputTokens), 0) AS outputTokens')
            ->addSelect('COALESCE(SUM(usage.cachedInputTokens), 0) AS cachedInputTokens')
            ->addSelect('COUNT(usage.id) AS calls')
            ->getQuery()
            ->getSingleResult();

        return $this->normalizeSummary($row);
    }

    /**
     * @return array<string, array{credits: int, inputTokens: int, outputTokens: int, cachedInputTokens: int, calls: int}>
     */
    public function getSummariesByUser(): array
    {
        /** @var list<array<string, int|string|null>> $rows */
        $rows = $this->createQueryBuilder('usage')
            ->select('IDENTITY(usage.user) AS userId')
            ->addSelect('COALESCE(SUM(usage.credits), 0) AS credits')
            ->addSelect('COALESCE(SUM(usage.inputTokens), 0) AS inputTokens')
            ->addSelect('COALESCE(SUM(usage.outputTokens), 0) AS outputTokens')
            ->addSelect('COALESCE(SUM(usage.cachedInputTokens), 0) AS cachedInputTokens')
            ->addSelect('COUNT(usage.id) AS calls')
            ->andWhere('usage.user IS NOT NULL')
            ->groupBy('usage.user')
            ->getQuery()
            ->getArrayResult();

        $summaries = [];
        foreach ($rows as $row) {
            $userId = (string) ($row['userId'] ?? '');
            if ('' !== $userId) {
                $summaries[$userId] = $this->normalizeSummary($row);
            }
        }

        return $summaries;
    }

    /**
     * @return array<string, array{credits: int, inputTokens: int, outputTokens: int, cachedInputTokens: int, calls: int}>
     */
    public function getSummariesByProject(): array
    {
        /** @var list<array<string, int|string|null>> $rows */
        $rows = $this->createQueryBuilder('usage')
            ->select('IDENTITY(usage.project) AS projectId')
            ->addSelect('COALESCE(SUM(usage.credits), 0) AS credits')
            ->addSelect('COALESCE(SUM(usage.inputTokens), 0) AS inputTokens')
            ->addSelect('COALESCE(SUM(usage.outputTokens), 0) AS outputTokens')
            ->addSelect('COALESCE(SUM(usage.cachedInputTokens), 0) AS cachedInputTokens')
            ->addSelect('COUNT(usage.id) AS calls')
            ->andWhere('usage.project IS NOT NULL')
            ->groupBy('usage.project')
            ->getQuery()
            ->getArrayResult();

        $summaries = [];
        foreach ($rows as $row) {
            $projectId = (string) ($row['projectId'] ?? '');
            if ('' !== $projectId) {
                $summaries[$projectId] = $this->normalizeSummary($row);
            }
        }

        return $summaries;
    }

    /** @return list<AiUsage> */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('usage')
            ->addSelect('user', 'project')
            ->leftJoin('usage.user', 'user')
            ->leftJoin('usage.project', 'project')
            ->orderBy('usage.createdAt', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }

    /** @return list<AiUsage> */
    public function findRecentForUser(User $user, int $limit = 20): array
    {
        return $this->createQueryBuilder('usage')
            ->addSelect('project')
            ->leftJoin('usage.project', 'project')
            ->andWhere('usage.user = :user')
            ->setParameter('user', $user)
            ->orderBy('usage.createdAt', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array<string, int|string|null> $row
     *
     * @return array{credits: int, inputTokens: int, outputTokens: int, cachedInputTokens: int, calls: int}
     */
    private function normalizeSummary(array $row): array
    {
        return [
            'credits' => (int) ($row['credits'] ?? 0),
            'inputTokens' => (int) ($row['inputTokens'] ?? 0),
            'outputTokens' => (int) ($row['outputTokens'] ?? 0),
            'cachedInputTokens' => (int) ($row['cachedInputTokens'] ?? 0),
            'calls' => (int) ($row['calls'] ?? 0),
        ];
    }
}
