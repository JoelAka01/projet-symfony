<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GeoDailySnapshot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<GeoDailySnapshot> */
class GeoDailySnapshotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GeoDailySnapshot::class);
    }
}
