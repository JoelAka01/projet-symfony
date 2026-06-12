<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AnalysisQuotaUsage;
use App\Entity\Audit;
use App\Entity\User;
use App\Enum\AnalysisQuotaStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AnalysisQuotaUsage> */
final class AnalysisQuotaUsageRepository extends ServiceEntityRepository
{
    private const ACTIVE_STATUSES = [
        'RESERVED',
        'CONSUMED',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AnalysisQuotaUsage::class);
    }

    public function countFreeForUser(User $user): int
    {
        return $this->countActiveBy('usage.user = :user', 'user', $user, null, 'FREE');
    }

    public function countFreeForIp(string $ipHash): int
    {
        return $this->countActiveBy('usage.ipHash = :ipHash', 'ipHash', $ipHash, null, 'FREE');
    }

    public function countForUserSince(User $user, \DateTimeImmutable $since): int
    {
        return $this->countActiveBy('usage.user = :user', 'user', $user, $since);
    }

    public function sumReservedCreditsForUserBetween(
        User $user,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): int {
        return (int) $this->createQueryBuilder('usage')
            ->select('COALESCE(SUM(usage.creditsCharged), 0)')
            ->andWhere('usage.user = :user')
            ->andWhere('usage.status = :status')
            ->andWhere('usage.createdAt >= :from')
            ->andWhere('usage.createdAt < :to')
            ->setParameter('user', $user)
            ->setParameter('status', AnalysisQuotaStatus::RESERVED)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findLatestReservedForAudit(Audit $audit): ?AnalysisQuotaUsage
    {
        return $this->createQueryBuilder('usage')
            ->andWhere('usage.audit = :audit')
            ->andWhere('usage.status = :status')
            ->setParameter('audit', $audit)
            ->setParameter('status', AnalysisQuotaStatus::RESERVED)
            ->orderBy('usage.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function countActiveBy(
        string $condition,
        string $parameterName,
        User|string $parameterValue,
        ?\DateTimeImmutable $since,
        ?string $planCode = null,
    ): int {
        $queryBuilder = $this->createQueryBuilder('usage')
            ->select('COUNT(usage.id)')
            ->andWhere($condition)
            ->andWhere('usage.status IN (:statuses)')
            ->setParameter($parameterName, $parameterValue)
            ->setParameter('statuses', self::ACTIVE_STATUSES);

        if (null !== $since) {
            $queryBuilder
                ->andWhere('usage.createdAt >= :since')
                ->setParameter('since', $since);
        }

        if (null !== $planCode) {
            $queryBuilder
                ->andWhere('usage.planCode = :planCode')
                ->setParameter('planCode', $planCode);
        }

        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }
}
