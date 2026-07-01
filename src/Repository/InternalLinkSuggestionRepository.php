<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Article;
use App\Entity\InternalLinkSuggestion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<InternalLinkSuggestion> */
class InternalLinkSuggestionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InternalLinkSuggestion::class);
    }

    /** @return list<InternalLinkSuggestion> */
    public function findForArticle(Article $article): array
    {
        return $this->createQueryBuilder('suggestion')
            ->addSelect('targetPage')
            ->leftJoin('suggestion.targetPage', 'targetPage')
            ->andWhere('suggestion.sourceArticle = :article')
            ->setParameter('article', $article)
            ->orderBy('suggestion.position', 'ASC')
            ->addOrderBy('suggestion.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
