<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\UuidPrimaryKeyTrait;
use App\Repository\KeywordRankingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: KeywordRankingRepository::class)]
#[ORM\Table(name: 'keyword_rankings')]
#[ORM\UniqueConstraint(name: 'uniq_keyword_rankings_daily', columns: ['keyword_id', 'search_engine', 'device', 'country', 'checked_at'])]
#[ORM\Index(name: 'idx_keyword_rankings_project_checked', columns: ['project_id', 'checked_at'])]
class KeywordRanking
{
    use UuidPrimaryKeyTrait;

    #[ORM\ManyToOne(inversedBy: 'rankings')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Keyword $keyword = null;

    #[ORM\ManyToOne(inversedBy: 'keywordRankings')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $rankPosition = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $previousRankPosition = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    private ?string $searchEngine = null;

    #[ORM\Column(length: 30, nullable: true)]
    #[Assert\Length(max: 30)]
    private ?string $device = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[Assert\Length(max: 10)]
    private ?string $country = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $checkedAt;

    public function __construct()
    {
        $this->initializeUuid();
        $this->checkedAt = new \DateTimeImmutable();
    }

    public function getKeyword(): ?Keyword
    {
        return $this->keyword;
    }

    public function setKeyword(?Keyword $keyword): self
    {
        $this->keyword = $keyword;

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

    public function getRankPosition(): ?int
    {
        return $this->rankPosition;
    }

    public function setRankPosition(?int $rankPosition): static
    {
        $this->rankPosition = $rankPosition;

        return $this;
    }

    public function getPreviousRankPosition(): ?int
    {
        return $this->previousRankPosition;
    }

    public function setPreviousRankPosition(?int $previousRankPosition): static
    {
        $this->previousRankPosition = $previousRankPosition;

        return $this;
    }

    public function getSearchEngine(): ?string
    {
        return $this->searchEngine;
    }

    public function setSearchEngine(?string $searchEngine): static
    {
        $this->searchEngine = $searchEngine;

        return $this;
    }

    public function getDevice(): ?string
    {
        return $this->device;
    }

    public function setDevice(?string $device): static
    {
        $this->device = $device;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function getCheckedAt(): ?\DateTimeImmutable
    {
        return $this->checkedAt;
    }

    public function setCheckedAt(\DateTimeImmutable $checkedAt): static
    {
        $this->checkedAt = $checkedAt;

        return $this;
    }
}
