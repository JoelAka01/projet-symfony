<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Article;
use App\Entity\Project;
use App\Enum\ArticleStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Article> */
class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

    /** @return list<Article> */
    public function findPublishedWithKeywords(Project $project): array
    {
        return $this->createQueryBuilder('article')
            ->addSelect('targetKeyword', 'primaryKeyword', 'cluster')
            ->leftJoin('article.targetKeywords', 'targetKeyword')
            ->leftJoin('article.primaryKeyword', 'primaryKeyword')
            ->leftJoin('article.keywordCluster', 'cluster')
            ->andWhere('article.project = :project')
            ->andWhere('article.status = :status')
            ->setParameter('project', $project)
            ->setParameter('status', ArticleStatus::PUBLISHED)
            ->orderBy('article.publishedAt', 'DESC')
            ->distinct()
            ->getQuery()
            ->getResult();
    }
}
