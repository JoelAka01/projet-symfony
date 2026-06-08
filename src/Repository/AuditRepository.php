<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Audit;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Audit> */
class AuditRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Audit::class);
    }

    public function findLatestForProject(Project $project): ?Audit
    {
        return $this->createQueryBuilder('audit')
            ->addSelect('domain', 'issue')
            ->leftJoin('audit.domain', 'domain')
            ->leftJoin('audit.issues', 'issue')
            ->andWhere('audit.project = :project')
            ->setParameter('project', $project)
            ->orderBy('audit.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
