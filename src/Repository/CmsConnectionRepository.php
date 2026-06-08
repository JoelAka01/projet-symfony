<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CmsConnection;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CmsConnection> */
class CmsConnectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CmsConnection::class);
    }
}
