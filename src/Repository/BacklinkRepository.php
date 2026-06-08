<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Backlink;
use App\Entity\Project;
use App\Enum\BacklinkStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Backlink> */
class BacklinkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Backlink::class);
    }

    /** @return list<Backlink> */
    public function findMarketplaceCandidates(Project $project): array
    {
        return $this->createQueryBuilder('backlink')
            ->addSelect('sourceProject', 'targetProject')
            ->innerJoin('backlink.sourceProject', 'sourceProject')
            ->innerJoin('backlink.targetProject', 'targetProject')
            ->andWhere('backlink.targetProject != :project')
            ->andWhere('backlink.status IN (:statuses)')
            ->setParameter('project', $project)
            ->setParameter('statuses', [BacklinkStatus::PROPOSED, BacklinkStatus::ACCEPTED])
            ->orderBy('backlink.qualityScore', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
