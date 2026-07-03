<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Project;
use App\Entity\ProjectApiBudget;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ProjectApiBudget> */
class ProjectApiBudgetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectApiBudget::class);
    }

    public function findForProject(Project $project): ?ProjectApiBudget
    {
        return $this->findOneBy(['project' => $project]);
    }
}
