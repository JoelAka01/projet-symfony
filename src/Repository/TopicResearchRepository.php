<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Project;
use App\Entity\TopicResearch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<TopicResearch> */
final class TopicResearchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TopicResearch::class);
    }

    /** @return list<TopicResearch> */
    public function findForProject(Project $project): array
    {
        return $this->createQueryBuilder('topicResearch')
            ->addSelect('serpAnalysis', 'intelligenceAnalysis', 'contentBrief', 'article')
            ->leftJoin('topicResearch.serpAnalysis', 'serpAnalysis')
            ->leftJoin('topicResearch.intelligenceAnalysis', 'intelligenceAnalysis')
            ->leftJoin('topicResearch.contentBrief', 'contentBrief')
            ->leftJoin('topicResearch.articles', 'article')
            ->andWhere('topicResearch.project = :project')
            ->setParameter('project', $project)
            ->orderBy('topicResearch.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
