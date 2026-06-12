<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Article;
use App\Entity\CmsConnection;
use App\Entity\CmsPublication;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CmsPublication> */
class CmsPublicationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CmsPublication::class);
    }

    public function findOneForArticleAndConnection(Article $article, CmsConnection $connection): ?CmsPublication
    {
        return $this->findOneBy([
            'article' => $article,
            'cmsConnection' => $connection,
        ]);
    }
}
