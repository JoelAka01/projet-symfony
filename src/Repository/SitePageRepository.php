<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Project;
use App\Entity\SitePage;
use App\Enum\SitePageType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SitePage> */
class SitePageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SitePage::class);
    }

    /** @return list<SitePage> */
    public function findActiveForProject(Project $project): array
    {
        return $this->createQueryBuilder('sitePage')
            ->andWhere('sitePage.project = :project')
            ->andWhere('sitePage.isActive = true')
            ->setParameter('project', $project)
            ->orderBy('sitePage.businessPriority', 'DESC')
            ->addOrderBy('sitePage.pageType', 'ASC')
            ->addOrderBy('sitePage.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<SitePage> */
    public function findForProject(Project $project): array
    {
        return $this->createQueryBuilder('sitePage')
            ->andWhere('sitePage.project = :project')
            ->setParameter('project', $project)
            ->orderBy('sitePage.isActive', 'DESC')
            ->addOrderBy('sitePage.businessPriority', 'DESC')
            ->addOrderBy('sitePage.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForProjectUrl(Project $project, string $url): ?SitePage
    {
        return $this->findOneBy([
            'project' => $project,
            'url' => $url,
        ]);
    }

    public function hasActiveType(Project $project, SitePageType ...$types): bool
    {
        return null !== $this->createQueryBuilder('sitePage')
            ->andWhere('sitePage.project = :project')
            ->andWhere('sitePage.isActive = true')
            ->andWhere('sitePage.pageType IN (:types)')
            ->setParameter('project', $project)
            ->setParameter('types', $types)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
