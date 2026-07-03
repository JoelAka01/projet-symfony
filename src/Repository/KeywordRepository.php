<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Keyword;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Keyword> */
class KeywordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Keyword::class);
    }

    /** @return list<Keyword> */
    public function searchForProject(Project $project, ?string $term = null, ?string $intent = null): array
    {
        $qb = $this->createQueryBuilder('keyword')
            ->addSelect('cluster')
            ->leftJoin('keyword.keywordCluster', 'cluster')
            ->andWhere('keyword.project = :project')
            ->setParameter('project', $project)
            ->orderBy('keyword.searchVolume', 'DESC');

        if (null !== $term && '' !== trim($term)) {
            $qb->andWhere('LOWER(keyword.term) LIKE :term')
                ->setParameter('term', '%' . mb_strtolower(trim($term)) . '%');
        }

        if (null !== $intent && '' !== trim($intent)) {
            $qb->andWhere('keyword.intent = :intent')
                ->setParameter('intent', $intent);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return list<Keyword> */
    public function findForProject(Project $project): array
    {
        return $this->createQueryBuilder('keyword')
            ->andWhere('keyword.project = :project')
            ->setParameter('project', $project)
            ->orderBy('keyword.term', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
