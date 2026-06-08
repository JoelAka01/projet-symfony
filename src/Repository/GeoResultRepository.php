<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GeoPrompt;
use App\Entity\GeoResult;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<GeoResult> */
class GeoResultRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GeoResult::class);
    }

    public function findLatestForPrompt(GeoPrompt $prompt): ?GeoResult
    {
        return $this->createQueryBuilder('result')
            ->andWhere('result.geoPrompt = :prompt')
            ->setParameter('prompt', $prompt)
            ->orderBy('result.checkedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
