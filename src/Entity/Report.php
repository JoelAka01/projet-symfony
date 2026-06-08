<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ReportStatus;
use App\Repository\ReportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReportRepository::class)]
#[ORM\Table(name: 'reports')]
#[ORM\Index(name: 'idx_reports_project_status', columns: ['status'])]
class Report extends ContentItem
{
    #[ORM\Column(enumType: ReportStatus::class)]
    private ReportStatus $status = ReportStatus::QUEUED;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $periodStart = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $periodEnd = null;

    #[ORM\Column(length: 1000, nullable: true)]
    #[Assert\Url]
    #[Assert\Length(max: 1000)]
    private ?string $storageUrl = null;

    #[ORM\Column(length: 1000, nullable: true)]
    #[Assert\Url]
    #[Assert\Length(max: 1000)]
    private ?string $csvExportUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Email]
    #[Assert\Length(max: 255)]
    private ?string $sentToEmail = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $generatedAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    public function getStatus(): ReportStatus
    {
        return $this->status;
    }

    public function setStatus(ReportStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getPeriodStart(): ?\DateTimeImmutable
    {
        return $this->periodStart;
    }

    public function setPeriodStart(?\DateTimeImmutable $periodStart): self
    {
        $this->periodStart = $periodStart;

        return $this;
    }

    public function getPeriodEnd(): ?\DateTimeImmutable
    {
        return $this->periodEnd;
    }

    public function setPeriodEnd(?\DateTimeImmutable $periodEnd): self
    {
        $this->periodEnd = $periodEnd;

        return $this;
    }

    public function getStorageUrl(): ?string
    {
        return $this->storageUrl;
    }

    public function setStorageUrl(?string $storageUrl): static
    {
        $this->storageUrl = $storageUrl;

        return $this;
    }

    public function getCsvExportUrl(): ?string
    {
        return $this->csvExportUrl;
    }

    public function setCsvExportUrl(?string $csvExportUrl): static
    {
        $this->csvExportUrl = $csvExportUrl;

        return $this;
    }

    public function getSentToEmail(): ?string
    {
        return $this->sentToEmail;
    }

    public function setSentToEmail(?string $sentToEmail): static
    {
        $this->sentToEmail = $sentToEmail;

        return $this;
    }

    public function getGeneratedAt(): ?\DateTimeImmutable
    {
        return $this->generatedAt;
    }

    public function setGeneratedAt(?\DateTimeImmutable $generatedAt): static
    {
        $this->generatedAt = $generatedAt;

        return $this;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTimeImmutable $sentAt): static
    {
        $this->sentAt = $sentAt;

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }
}
