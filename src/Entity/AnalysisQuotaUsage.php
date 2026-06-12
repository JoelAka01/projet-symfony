<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\UuidPrimaryKeyTrait;
use App\Enum\AnalysisQuotaStatus;
use App\Repository\AnalysisQuotaUsageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AnalysisQuotaUsageRepository::class)]
#[ORM\Table(name: 'analysis_quota_usages')]
#[ORM\Index(name: 'idx_analysis_quota_user_created', columns: ['user_id', 'created_at'])]
#[ORM\Index(name: 'idx_analysis_quota_ip_created', columns: ['ip_hash', 'created_at'])]
#[ORM\Index(name: 'idx_analysis_quota_audit_status', columns: ['audit_id', 'status'])]
class AnalysisQuotaUsage
{
    use UuidPrimaryKeyTrait;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Audit $audit = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Subscription $subscription = null;

    #[ORM\Column(length: 20)]
    private string $planCode = 'FREE';

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $ipHash = null;

    #[ORM\Column]
    private int $creditsCharged = 0;

    #[ORM\Column(enumType: AnalysisQuotaStatus::class)]
    private AnalysisQuotaStatus $status = AnalysisQuotaStatus::RESERVED;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $finalizedAt = null;

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

    public function getAudit(): ?Audit
    {
        return $this->audit;
    }

    public function setAudit(?Audit $audit): self
    {
        $this->audit = $audit;

        return $this;
    }

    public function getSubscription(): ?Subscription
    {
        return $this->subscription;
    }

    public function setSubscription(?Subscription $subscription): self
    {
        $this->subscription = $subscription;

        return $this;
    }

    public function getPlanCode(): string
    {
        return $this->planCode;
    }

    public function setPlanCode(string $planCode): self
    {
        $this->planCode = strtoupper(trim($planCode));

        return $this;
    }

    public function getIpHash(): ?string
    {
        return $this->ipHash;
    }

    public function setIpHash(?string $ipHash): self
    {
        $this->ipHash = $ipHash;

        return $this;
    }

    public function getCreditsCharged(): int
    {
        return $this->creditsCharged;
    }

    public function setCreditsCharged(int $creditsCharged): self
    {
        $this->creditsCharged = max(0, $creditsCharged);

        return $this;
    }

    public function getStatus(): AnalysisQuotaStatus
    {
        return $this->status;
    }

    public function setStatus(AnalysisQuotaStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getFinalizedAt(): ?\DateTimeImmutable
    {
        return $this->finalizedAt;
    }

    public function setFinalizedAt(?\DateTimeImmutable $finalizedAt): self
    {
        $this->finalizedAt = $finalizedAt;

        return $this;
    }
}
