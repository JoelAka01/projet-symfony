<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Project;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Project> */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    /** @return list<Project> */
    public function findDashboardProjectsForUser(User $user): array
    {
        return $this->createQueryBuilder('project')
            ->addSelect('organization', 'domain')
            ->leftJoin('project.organization', 'organization')
            ->leftJoin('project.domains', 'domain')
            ->leftJoin('project.guests', 'projectGuest')
            ->leftJoin('organization.organizationUsers', 'organizationUser')
            ->andWhere('project.owner = :user OR projectGuest = :user OR organizationUser.user = :user')
            ->setParameter('user', $user)
            ->orderBy('project.updatedAt', 'DESC')
            ->distinct()
            ->getQuery()
            ->getResult();
    }
}
