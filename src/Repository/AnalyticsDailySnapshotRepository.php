<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AnalyticsDailySnapshot;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AnalyticsDailySnapshot> */
class AnalyticsDailySnapshotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AnalyticsDailySnapshot::class);
    }

    public function findLatestForProject(Project $project): ?AnalyticsDailySnapshot
    {
        return $this->createQueryBuilder('snapshot')
            ->andWhere('snapshot.project = :project')
            ->setParameter('project', $project)
            ->orderBy('snapshot.snapshotDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
