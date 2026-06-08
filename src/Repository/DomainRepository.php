<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Domain;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Domain> */
class DomainRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Domain::class);
    }

    /** @return list<Domain> */
    public function findVerifiedForProject(Project $project): array
    {
        return $this->createQueryBuilder('domain')
            ->andWhere('domain.project = :project')
            ->andWhere('domain.verifiedAt IS NOT NULL')
            ->setParameter('project', $project)
            ->orderBy('domain.rootDomain', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
