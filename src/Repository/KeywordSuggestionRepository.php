<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\KeywordSuggestion;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<KeywordSuggestion> */
class KeywordSuggestionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, KeywordSuggestion::class);
    }

    /** @return list<KeywordSuggestion> */
    public function findForProject(Project $project, int $limit = 50): array
    {
        return $this->createQueryBuilder('suggestion')
            ->andWhere('suggestion.project = :project')
            ->setParameter('project', $project)
            ->orderBy('suggestion.opportunityScore', 'DESC')
            ->addOrderBy('suggestion.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return list<KeywordSuggestion> */
    public function findSelectedForProject(Project $project): array
    {
        return $this->createQueryBuilder('suggestion')
            ->andWhere('suggestion.project = :project')
            ->andWhere('suggestion.isSelected = true')
            ->setParameter('project', $project)
            ->orderBy('suggestion.opportunityScore', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByNormalizedTerm(Project $project, string $normalizedTerm): ?KeywordSuggestion
    {
        return $this->createQueryBuilder('suggestion')
            ->andWhere('suggestion.project = :project')
            ->andWhere('suggestion.normalizedTerm = :normalizedTerm')
            ->setParameter('project', $project)
            ->setParameter('normalizedTerm', $normalizedTerm)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
