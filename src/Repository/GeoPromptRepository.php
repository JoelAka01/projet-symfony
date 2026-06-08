<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GeoPrompt;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<GeoPrompt> */
class GeoPromptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GeoPrompt::class);
    }
}
