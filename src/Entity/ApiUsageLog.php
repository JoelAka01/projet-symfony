<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\UuidPrimaryKeyTrait;
use App\Repository\ApiUsageLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ApiUsageLogRepository::class)]
#[ORM\Table(name: 'api_usage_logs')]
#[ORM\Index(name: 'idx_api_usage_logs_project_created', columns: ['project_id', 'created_at'])]
#[ORM\Index(name: 'idx_api_usage_logs_provider_operation', columns: ['provider', 'operation'])]
class ApiUsageLog
{
    use UuidPrimaryKeyTrait;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Project $project = null;

    #[ORM\Column(length: 80)]
    private string $provider = '';

    #[ORM\Column(length: 120)]
    private string $operation = '';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 6)]
    private string $estimatedCost = '0.000000';

    #[ORM\Column]
    private int $tokensInput = 0;

    #[ORM\Column]
    private int $tokensOutput = 0;

    #[ORM\Column]
    private bool $cacheHit = false;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 6)]
    private string $savedCostEstimate = '0.000000';

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->initializeUuid();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): self
    {
        $this->project = $project;

        return $this;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function setOperation(string $operation): self
    {
        $this->operation = $operation;

        return $this;
    }

    public function getEstimatedCost(): string
    {
        return $this->estimatedCost;
    }

    public function setEstimatedCost(string $estimatedCost): self
    {
        $this->estimatedCost = $estimatedCost;

        return $this;
    }

    public function getTokensInput(): int
    {
        return $this->tokensInput;
    }

    public function setTokensInput(int $tokensInput): self
    {
        $this->tokensInput = max(0, $tokensInput);

        return $this;
    }

    public function getTokensOutput(): int
    {
        return $this->tokensOutput;
    }

    public function setTokensOutput(int $tokensOutput): self
    {
        $this->tokensOutput = max(0, $tokensOutput);

        return $this;
    }

    public function isCacheHit(): bool
    {
        return $this->cacheHit;
    }

    public function setCacheHit(bool $cacheHit): self
    {
        $this->cacheHit = $cacheHit;

        return $this;
    }

    public function getSavedCostEstimate(): string
    {
        return $this->savedCostEstimate;
    }

    public function setSavedCostEstimate(string $savedCostEstimate): self
    {
        $this->savedCostEstimate = $savedCostEstimate;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
