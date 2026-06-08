<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\UuidPrimaryKeyTrait;
use App\Repository\AuditIssueRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AuditIssueRepository::class)]
#[ORM\Table(name: 'audit_issues')]
#[ORM\Index(name: 'idx_audit_issues_audit_severity', columns: ['audit_id', 'severity'])]
class AuditIssue
{
    use UuidPrimaryKeyTrait;

    #[ORM\ManyToOne(inversedBy: 'issues')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Audit $audit = null;

    #[ORM\ManyToOne(inversedBy: 'issues')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?AuditPage $auditPage = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    private string $issueType = '';

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    #[Assert\Choice(['info', 'low', 'medium', 'high', 'critical'])]
    private string $severity = 'medium';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $message = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $recommendation = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->initializeUuid();
        $this->createdAt = new \DateTimeImmutable();
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

    public function getAuditPage(): ?AuditPage
    {
        return $this->auditPage;
    }

    public function setAuditPage(?AuditPage $auditPage): self
    {
        $this->auditPage = $auditPage;

        return $this;
    }

    public function getIssueType(): string
    {
        return $this->issueType;
    }

    public function setIssueType(string $issueType): self
    {
        $this->issueType = $issueType;

        return $this;
    }

    public function getSeverity(): ?string
    {
        return $this->severity;
    }

    public function setSeverity(string $severity): static
    {
        $this->severity = $severity;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getRecommendation(): ?string
    {
        return $this->recommendation;
    }

    public function setRecommendation(?string $recommendation): static
    {
        $this->recommendation = $recommendation;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
