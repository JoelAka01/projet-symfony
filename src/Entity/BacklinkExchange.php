<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\UuidPrimaryKeyTrait;
use App\Enum\BacklinkStatus;
use App\Repository\BacklinkExchangeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BacklinkExchangeRepository::class)]
#[ORM\Table(name: 'backlink_exchanges')]
#[ORM\Index(name: 'idx_backlink_exchanges_status', columns: ['status'])]
class BacklinkExchange
{
    use UuidPrimaryKeyTrait;

    #[ORM\ManyToOne(inversedBy: 'requesterBacklinkExchanges')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $requesterProject = null;

    #[ORM\ManyToOne(inversedBy: 'publisherBacklinkExchanges')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $publisherProject = null;

    #[ORM\ManyToOne(inversedBy: 'exchanges')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Backlink $backlink = null;

    #[ORM\Column(enumType: BacklinkStatus::class)]
    private BacklinkStatus $status = BacklinkStatus::PROPOSED;

    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    private ?int $matchScore = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $requestedAnchorText = null;

    #[ORM\Column(length: 1000, nullable: true)]
    #[Assert\Url]
    #[Assert\Length(max: 1000)]
    private ?string $requestedTargetUrl = null;

    #[ORM\Column(length: 1000, nullable: true)]
    #[Assert\Url]
    #[Assert\Length(max: 1000)]
    private ?string $proposedSourceUrl = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $acceptedAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    public function __construct()
    {
        $this->initializeUuid();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getRequesterProject(): ?Project
    {
        return $this->requesterProject;
    }

    public function setRequesterProject(?Project $requesterProject): self
    {
        $this->requesterProject = $requesterProject;

        return $this;
    }

    public function getPublisherProject(): ?Project
    {
        return $this->publisherProject;
    }

    public function setPublisherProject(?Project $publisherProject): self
    {
        $this->publisherProject = $publisherProject;

        return $this;
    }

    public function getStatus(): ?BacklinkStatus
    {
        return $this->status;
    }

    public function setStatus(BacklinkStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getMatchScore(): ?int
    {
        return $this->matchScore;
    }

    public function setMatchScore(?int $matchScore): static
    {
        $this->matchScore = $matchScore;

        return $this;
    }

    public function getRequestedAnchorText(): ?string
    {
        return $this->requestedAnchorText;
    }

    public function setRequestedAnchorText(?string $requestedAnchorText): static
    {
        $this->requestedAnchorText = $requestedAnchorText;

        return $this;
    }

    public function getRequestedTargetUrl(): ?string
    {
        return $this->requestedTargetUrl;
    }

    public function setRequestedTargetUrl(?string $requestedTargetUrl): static
    {
        $this->requestedTargetUrl = $requestedTargetUrl;

        return $this;
    }

    public function getProposedSourceUrl(): ?string
    {
        return $this->proposedSourceUrl;
    }

    public function setProposedSourceUrl(?string $proposedSourceUrl): static
    {
        $this->proposedSourceUrl = $proposedSourceUrl;

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

    public function getAcceptedAt(): ?\DateTimeImmutable
    {
        return $this->acceptedAt;
    }

    public function setAcceptedAt(?\DateTimeImmutable $acceptedAt): static
    {
        $this->acceptedAt = $acceptedAt;

        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;

        return $this;
    }

    public function getBacklink(): ?Backlink
    {
        return $this->backlink;
    }

    public function setBacklink(?Backlink $backlink): static
    {
        $this->backlink = $backlink;

        return $this;
    }
}
