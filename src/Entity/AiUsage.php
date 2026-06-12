<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\UuidPrimaryKeyTrait;
use App\Repository\AiUsageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AiUsageRepository::class)]
#[ORM\Table(name: 'ai_usages')]
#[ORM\Index(name: 'idx_ai_usages_user_created', columns: ['user_id', 'created_at'])]
#[ORM\Index(name: 'idx_ai_usages_project_created', columns: ['project_id', 'created_at'])]
#[ORM\Index(name: 'idx_ai_usages_operation', columns: ['operation'])]
class AiUsage
{
    use UuidPrimaryKeyTrait;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Project $project = null;

    #[ORM\Column(length: 50)]
    private string $provider = '';

    #[ORM\Column(length: 120)]
    private string $model = '';

    #[ORM\Column(length: 50)]
    private string $operation = '';

    #[ORM\Column]
    private int $inputTokens = 0;

    #[ORM\Column]
    private int $outputTokens = 0;

    #[ORM\Column]
    private int $cachedInputTokens = 0;

    #[ORM\Column]
    private int $credits = 0;

    #[ORM\Column(type: 'guid', nullable: true)]
    private ?string $resourceId = null;

    #[ORM\Column(type: Types::JSON, nullable: true, options: ['jsonb' => true])]
    private ?array $providerUsage = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->initializeUuid();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
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
        $this->provider = trim($provider);

        return $this;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function setModel(string $model): self
    {
        $this->model = trim($model);

        return $this;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function setOperation(string $operation): self
    {
        $this->operation = trim($operation);

        return $this;
    }

    public function getInputTokens(): int
    {
        return $this->inputTokens;
    }

    public function setInputTokens(int $inputTokens): self
    {
        $this->inputTokens = max(0, $inputTokens);

        return $this;
    }

    public function getOutputTokens(): int
    {
        return $this->outputTokens;
    }

    public function setOutputTokens(int $outputTokens): self
    {
        $this->outputTokens = max(0, $outputTokens);

        return $this;
    }

    public function getCachedInputTokens(): int
    {
        return $this->cachedInputTokens;
    }

    public function setCachedInputTokens(int $cachedInputTokens): self
    {
        $this->cachedInputTokens = max(0, $cachedInputTokens);

        return $this;
    }

    public function getCredits(): int
    {
        return $this->credits;
    }

    public function setCredits(int $credits): self
    {
        $this->credits = max(0, $credits);

        return $this;
    }

    public function getResourceId(): ?string
    {
        return $this->resourceId;
    }

    public function setResourceId(?string $resourceId): self
    {
        $this->resourceId = $resourceId;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getProviderUsage(): ?array
    {
        return $this->providerUsage;
    }

    /** @param array<string, mixed>|null $providerUsage */
    public function setProviderUsage(?array $providerUsage): self
    {
        $this->providerUsage = $providerUsage;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
