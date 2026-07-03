<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AiCache;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AiCache> */
class AiCacheRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiCache::class);
    }

    public function findReusable(string $operation, string $inputHash, string $model): ?AiCache
    {
        return $this->createQueryBuilder('cache')
            ->andWhere('cache.operation = :operation')
            ->andWhere('cache.inputHash = :inputHash')
            ->andWhere('cache.model = :model')
            ->setParameter('operation', $operation)
            ->setParameter('inputHash', $inputHash)
            ->setParameter('model', $model)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
