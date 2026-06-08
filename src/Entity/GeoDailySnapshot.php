<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\UuidPrimaryKeyTrait;
use App\Repository\GeoDailySnapshotRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: GeoDailySnapshotRepository::class)]
#[ORM\Table(name: 'geo_daily_snapshots')]
#[ORM\UniqueConstraint(name: 'uniq_geo_daily_snapshot_project_date', columns: ['project_id', 'snapshot_date'])]
class GeoDailySnapshot
{
    use UuidPrimaryKeyTrait;

    #[ORM\ManyToOne(inversedBy: 'geoDailySnapshots')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $snapshotDate;

    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    private ?int $geoScore = null;

    #[ORM\Column(nullable: true)]
    private ?int $promptsChecked = null;

    #[ORM\Column(nullable: true)]
    private ?int $mentionsCount = null;

    #[ORM\Column(nullable: true)]
    private ?int $citationsCount = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['jsonb' => true])]
    private ?array $competitorsJson = null;

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

    public function getGeoScore(): ?int
    {
        return $this->geoScore;
    }

    public function setGeoScore(?int $geoScore): static
    {
        $this->geoScore = $geoScore;

        return $this;
    }

    public function getPromptsChecked(): ?int
    {
        return $this->promptsChecked;
    }

    public function setPromptsChecked(?int $promptsChecked): static
    {
        $this->promptsChecked = $promptsChecked;

        return $this;
    }

    public function getMentionsCount(): ?int
    {
        return $this->mentionsCount;
    }

    public function setMentionsCount(?int $mentionsCount): static
    {
        $this->mentionsCount = $mentionsCount;

        return $this;
    }

    public function getCitationsCount(): ?int
    {
        return $this->citationsCount;
    }

    public function setCitationsCount(?int $citationsCount): static
    {
        $this->citationsCount = $citationsCount;

        return $this;
    }

    public function getCompetitorsJson(): ?array
    {
        return $this->competitorsJson;
    }

    public function setCompetitorsJson(?array $competitorsJson): static
    {
        $this->competitorsJson = $competitorsJson;

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
