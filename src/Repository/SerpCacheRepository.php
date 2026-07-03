<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SerpCache;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SerpCache> */
class SerpCacheRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SerpCache::class);
    }

    public function findFresh(string $keyword, string $country, string $language, string $provider): ?SerpCache
    {
        return $this->createQueryBuilder('cache')
            ->andWhere('cache.keyword = :keyword')
            ->andWhere('cache.country = :country')
            ->andWhere('cache.language = :language')
            ->andWhere('cache.provider = :provider')
            ->andWhere('cache.expiresAt > :now')
            ->setParameter('keyword', mb_strtolower(trim($keyword)))
            ->setParameter('country', strtoupper(trim($country)))
            ->setParameter('language', strtolower(trim($language)))
            ->setParameter('provider', $provider)
            ->setParameter('now', new \DateTimeImmutable())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findAny(string $keyword, string $country, string $language, string $provider): ?SerpCache
    {
        return $this->createQueryBuilder('cache')
            ->andWhere('cache.keyword = :keyword')
            ->andWhere('cache.country = :country')
            ->andWhere('cache.language = :language')
            ->andWhere('cache.provider = :provider')
            ->setParameter('keyword', mb_strtolower(trim($keyword)))
            ->setParameter('country', strtoupper(trim($country)))
            ->setParameter('language', strtolower(trim($language)))
            ->setParameter('provider', $provider)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
