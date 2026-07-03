<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ApiUsageLog;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ApiUsageLog> */
class ApiUsageLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiUsageLog::class);
    }

    public function sumCostSince(Project $project, \DateTimeImmutable $since): float
    {
        $value = $this->createQueryBuilder('log')
            ->select('COALESCE(SUM(log.estimatedCost), 0)')
            ->andWhere('log.project = :project')
            ->andWhere('log.createdAt >= :since')
            ->setParameter('project', $project)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();

        return is_numeric($value) ? (float) $value : 0.0;
    }

    public function sumAiTokensSince(Project $project, \DateTimeImmutable $since): int
    {
        $value = $this->createQueryBuilder('log')
            ->select('COALESCE(SUM(log.tokensInput + log.tokensOutput), 0)')
            ->andWhere('log.project = :project')
            ->andWhere('log.createdAt >= :since')
            ->andWhere('log.provider IN (:providers)')
            ->setParameter('project', $project)
            ->setParameter('since', $since)
            ->setParameter('providers', ['anthropic', 'claude'])
            ->getQuery()
            ->getSingleScalarResult();

        return is_numeric($value) ? (int) $value : 0;
    }

    public function countSerpCallsSince(Project $project, \DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('log')
            ->select('COUNT(log.id)')
            ->andWhere('log.project = :project')
            ->andWhere('log.createdAt >= :since')
            ->andWhere('log.provider IN (:providers)')
            ->andWhere('log.cacheHit = false')
            ->setParameter('project', $project)
            ->setParameter('since', $since)
            ->setParameter('providers', ['zenserp', 'dataforseo'])
            ->getQuery()
            ->getSingleScalarResult();
    }
}
