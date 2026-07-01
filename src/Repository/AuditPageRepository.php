<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AuditPage;
use App\Entity\Project;
use App\Enum\AuditStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AuditPage> */
class AuditPageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditPage::class);
    }

    /** @return list<AuditPage> */
    public function findIndexablePagesForLatestCompletedAudit(Project $project, int $limit = 80): array
    {
        return $this->createQueryBuilder('page')
            ->innerJoin('page.audit', 'audit')
            ->andWhere('audit.project = :project')
            ->andWhere('audit.status = :status')
            ->andWhere('page.isIndexable = true')
            ->andWhere('page.statusCode >= 200')
            ->andWhere('page.statusCode < 400')
            ->setParameter('project', $project)
            ->setParameter('status', AuditStatus::COMPLETED)
            ->orderBy('audit.createdAt', 'DESC')
            ->addOrderBy('page.wordCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
