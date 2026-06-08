<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\UuidPrimaryKeyTrait;
use App\Enum\BacklinkStatus;
use App\Repository\BacklinkRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BacklinkRepository::class)]
#[ORM\Table(name: 'backlinks')]
#[ORM\Index(name: 'idx_backlinks_status', columns: ['status'])]
#[ORM\Index(name: 'idx_backlinks_projects', columns: ['source_project_id', 'target_project_id'])]
class Backlink
{
    use UuidPrimaryKeyTrait;

    #[ORM\ManyToOne(inversedBy: 'sourceBacklinks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $sourceProject = null;

    #[ORM\ManyToOne(inversedBy: 'targetBacklinks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $targetProject = null;

    #[ORM\Column(length: 1000, nullable: true)]
    #[Assert\Url]
    #[Assert\Length(max: 1000)]
    private ?string $sourceUrl = null;

    #[ORM\Column(length: 1000, nullable: true)]
    #[Assert\Url]
    #[Assert\Length(max: 1000)]
    private ?string $targetUrl = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $anchorText = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $contextText = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    private ?int $qualityScore = null;

    #[ORM\Column(enumType: BacklinkStatus::class)]
    private BacklinkStatus $status = BacklinkStatus::PROPOSED;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $firstDetectedAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastCheckedAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $removedAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, BacklinkExchange> */
    #[ORM\OneToMany(mappedBy: 'backlink', targetEntity: BacklinkExchange::class)]
    private Collection $exchanges;

    public function __construct()
    {
        $this->initializeUuid();
        $this->createdAt = new \DateTimeImmutable();
        $this->exchanges = new ArrayCollection();
    }

    public function getSourceProject(): ?Project
    {
        return $this->sourceProject;
    }

    public function setSourceProject(?Project $sourceProject): self
    {
        $this->sourceProject = $sourceProject;

        return $this;
    }

    public function getTargetProject(): ?Project
    {
        return $this->targetProject;
    }

    public function setTargetProject(?Project $targetProject): self
    {
        $this->targetProject = $targetProject;

        return $this;
    }

    public function getStatus(): BacklinkStatus
    {
        return $this->status;
    }

    public function setStatus(BacklinkStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getSourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    public function setSourceUrl(?string $sourceUrl): static
    {
        $this->sourceUrl = $sourceUrl;

        return $this;
    }

    public function getTargetUrl(): ?string
    {
        return $this->targetUrl;
    }

    public function setTargetUrl(?string $targetUrl): static
    {
        $this->targetUrl = $targetUrl;

        return $this;
    }

    public function getAnchorText(): ?string
    {
        return $this->anchorText;
    }

    public function setAnchorText(?string $anchorText): static
    {
        $this->anchorText = $anchorText;

        return $this;
    }

    public function getContextText(): ?string
    {
        return $this->contextText;
    }

    public function setContextText(?string $contextText): static
    {
        $this->contextText = $contextText;

        return $this;
    }

    public function getQualityScore(): ?int
    {
        return $this->qualityScore;
    }

    public function setQualityScore(?int $qualityScore): static
    {
        $this->qualityScore = $qualityScore;

        return $this;
    }

    public function getFirstDetectedAt(): ?\DateTimeImmutable
    {
        return $this->firstDetectedAt;
    }

    public function setFirstDetectedAt(?\DateTimeImmutable $firstDetectedAt): static
    {
        $this->firstDetectedAt = $firstDetectedAt;

        return $this;
    }

    public function getLastCheckedAt(): ?\DateTimeImmutable
    {
        return $this->lastCheckedAt;
    }

    public function setLastCheckedAt(?\DateTimeImmutable $lastCheckedAt): static
    {
        $this->lastCheckedAt = $lastCheckedAt;

        return $this;
    }

    public function getRemovedAt(): ?\DateTimeImmutable
    {
        return $this->removedAt;
    }

    public function setRemovedAt(?\DateTimeImmutable $removedAt): static
    {
        $this->removedAt = $removedAt;

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

    /**
     * @return Collection<int, BacklinkExchange>
     */
    public function getExchanges(): Collection
    {
        return $this->exchanges;
    }

    public function addExchange(BacklinkExchange $exchange): static
    {
        if (!$this->exchanges->contains($exchange)) {
            $this->exchanges->add($exchange);
            $exchange->setBacklink($this);
        }

        return $this;
    }

    public function removeExchange(BacklinkExchange $exchange): static
    {
        if ($this->exchanges->removeElement($exchange)) {
            // set the owning side to null (unless already changed)
            if ($exchange->getBacklink() === $this) {
                $exchange->setBacklink(null);
            }
        }

        return $this;
    }
}
