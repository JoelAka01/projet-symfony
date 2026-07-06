<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Project;
use App\Entity\User;
use App\Enum\ProjectStatus;
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
            ->leftJoin('project.projectGuests', 'projectGuestMembership')
            ->leftJoin('projectGuestMembership.user', 'projectGuestUser')
            ->leftJoin('organization.organizationUsers', 'organizationUser')
            ->andWhere('project.owner = :user OR projectGuestUser = :user OR organizationUser.user = :user')
            ->setParameter('user', $user)
            ->orderBy('project.updatedAt', 'DESC')
            ->distinct()
            ->getQuery()
            ->getResult();
    }

    /** @return list<Project> */
    public function findForAdmin(?string $search = null, ?ProjectStatus $status = null): array
    {
        $queryBuilder = $this->createQueryBuilder('project')
            ->addSelect('owner', 'organization', 'domain')
            ->leftJoin('project.owner', 'owner')
            ->leftJoin('project.organization', 'organization')
            ->leftJoin('project.domains', 'domain')
            ->orderBy('project.updatedAt', 'DESC')
            ->distinct();

        $search = trim((string) $search);
        if ('' !== $search) {
            $queryBuilder
                ->andWhere('LOWER(project.name) LIKE :search OR LOWER(owner.email) LIKE :search OR LOWER(domain.rootDomain) LIKE :search')
                ->setParameter('search', '%' . strtolower($search) . '%');
        }

        if (null !== $status) {
            $queryBuilder
                ->andWhere('project.status = :status')
                ->setParameter('status', $status);
        }

        return $queryBuilder->getQuery()->getResult();
    }
}
