<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\UuidPrimaryKeyTrait;
use App\Repository\AnalyticsDailySnapshotRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AnalyticsDailySnapshotRepository::class)]
#[ORM\Table(name: 'analytics_daily_snapshots')]
#[ORM\UniqueConstraint(name: 'uniq_analytics_daily_snapshot_project_date', columns: ['project_id', 'snapshot_date'])]
class AnalyticsDailySnapshot
{
    use UuidPrimaryKeyTrait;

    #[ORM\ManyToOne(inversedBy: 'analyticsDailySnapshots')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $snapshotDate;

    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    private ?int $seoScore = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    private ?int $geoScore = null;

    #[ORM\Column(nullable: true)]
    private ?int $organicTraffic = null;

    #[ORM\Column(nullable: true)]
    private ?int $backlinksCount = null;

    #[ORM\Column(nullable: true)]
    private ?int $publishedArticlesCount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $estimatedRoi = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['jsonb' => true])]
    private ?array $metricsJson = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->initializeUuid();
        $this->snapshotDate = new \DateTimeImmutable();
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

    public function getSnapshotDate(): ?\DateTimeImmutable
    {
        return $this->snapshotDate;
    }

    public function setSnapshotDate(\DateTimeImmutable $snapshotDate): static
    {
        $this->snapshotDate = $snapshotDate;

        return $this;
    }

    public function getSeoScore(): ?int
    {
        return $this->seoScore;
    }

    public function setSeoScore(?int $seoScore): static
    {
        $this->seoScore = $seoScore;

        return $this;
    }

    public function getGeoScore(): ?int
    {
        return $this->geoScore;
    }

    public function setGeoScore(?int $geoScore): static
    {
        $this->geoScore = $geoScore;

        return $this;
    }

    public function getOrganicTraffic(): ?int
    {
        return $this->organicTraffic;
    }

    public function setOrganicTraffic(?int $organicTraffic): static
    {
        $this->organicTraffic = $organicTraffic;

        return $this;
    }

    public function getBacklinksCount(): ?int
    {
        return $this->backlinksCount;
    }

    public function setBacklinksCount(?int $backlinksCount): static
    {
        $this->backlinksCount = $backlinksCount;

        return $this;
    }

    public function getPublishedArticlesCount(): ?int
    {
        return $this->publishedArticlesCount;
    }

    public function setPublishedArticlesCount(?int $publishedArticlesCount): static
    {
        $this->publishedArticlesCount = $publishedArticlesCount;

        return $this;
    }

    public function getEstimatedRoi(): ?string
    {
        return $this->estimatedRoi;
    }

    public function setEstimatedRoi(?string $estimatedRoi): static
    {
        $this->estimatedRoi = $estimatedRoi;

        return $this;
    }

    public function getMetricsJson(): ?array
    {
        return $this->metricsJson;
    }

    public function setMetricsJson(?array $metricsJson): static
    {
        $this->metricsJson = $metricsJson;

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
