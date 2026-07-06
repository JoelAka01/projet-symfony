<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PipelineStepConfig;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PipelineStepConfig> */
class PipelineStepConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PipelineStepConfig::class);
    }

    public function findOneByStepKey(string $stepKey): ?PipelineStepConfig
    {
        return $this->findOneBy(['stepKey' => strtoupper(trim($stepKey))]);
    }

    /** @return array<string, PipelineStepConfig> */
    public function findIndexedByStepKey(): array
    {
        $configs = [];
        foreach ($this->findAll() as $config) {
            $configs[$config->getStepKey()] = $config;
        }

        return $configs;
    }
}
