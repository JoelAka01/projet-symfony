<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BacklinkSite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<BacklinkSite> */
class BacklinkSiteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BacklinkSite::class);
    }
}
