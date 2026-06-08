<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\KeywordRanking;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<KeywordRanking> */
class KeywordRankingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, KeywordRanking::class);
    }
}
