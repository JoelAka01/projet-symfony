<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\UuidPrimaryKeyTrait;
use App\Enum\AuditStatus;
use App\Repository\AuditRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AuditRepository::class)]
#[ORM\Table(name: 'audits')]
#[ORM\Index(name: 'idx_audits_project_status', columns: ['project_id', 'status'])]
class Audit
{
    use UuidPrimaryKeyTrait;

    #[ORM\ManyToOne(inversedBy: 'audits')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\ManyToOne(inversedBy: 'audits')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Domain $domain = null;

    #[ORM\Column(enumType: AuditStatus::class)]
    private AuditStatus $status = AuditStatus::QUEUED;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $crawlStartedAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $crawlFinishedAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $pagesCrawled = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    private ?int $seoScore = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    private ?int $coreWebVitalsScore = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['jsonb' => true])]
    private ?array $metadata = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, AuditPage> */
    #[ORM\OneToMany(mappedBy: 'audit', targetEntity: AuditPage::class, orphanRemoval: true)]
    private Collection $pages;

    /** @var Collection<int, AuditIssue> */
    #[ORM\OneToMany(mappedBy: 'audit', targetEntity: AuditIssue::class, orphanRemoval: true)]
    private Collection $issues;

    public function __construct()
    {
        $this->initializeUuid();
        $this->createdAt = new \DateTimeImmutable();
        $this->pages = new ArrayCollection();
        $this->issues = new ArrayCollection();
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

    public function getDomain(): ?Domain
    {
        return $this->domain;
    }

    public function setDomain(?Domain $domain): self
    {
        $this->domain = $domain;

        return $this;
    }

    public function getStatus(): AuditStatus
    {
        return $this->status;
    }

    public function setStatus(AuditStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getSeoScore(): ?int
    {
        return $this->seoScore;
    }

    public function setSeoScore(?int $seoScore): self
    {
        $this->seoScore = $seoScore;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, AuditIssue> */
    public function getIssues(): Collection
    {
        return $this->issues;
    }

    public function getCrawlStartedAt(): ?\DateTimeImmutable
    {
        return $this->crawlStartedAt;
    }

    public function setCrawlStartedAt(?\DateTimeImmutable $crawlStartedAt): static
    {
        $this->crawlStartedAt = $crawlStartedAt;

        return $this;
    }

    public function getCrawlFinishedAt(): ?\DateTimeImmutable
    {
        return $this->crawlFinishedAt;
    }

    public function setCrawlFinishedAt(?\DateTimeImmutable $crawlFinishedAt): static
    {
        $this->crawlFinishedAt = $crawlFinishedAt;

        return $this;
    }

    public function getPagesCrawled(): ?int
    {
        return $this->pagesCrawled;
    }

    public function setPagesCrawled(?int $pagesCrawled): static
    {
        $this->pagesCrawled = $pagesCrawled;

        return $this;
    }

    public function getCoreWebVitalsScore(): ?int
    {
        return $this->coreWebVitalsScore;
    }

    public function setCoreWebVitalsScore(?int $coreWebVitalsScore): static
    {
        $this->coreWebVitalsScore = $coreWebVitalsScore;

        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;

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

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return Collection<int, AuditPage>
     */
    public function getPages(): Collection
    {
        return $this->pages;
    }

    public function addPage(AuditPage $page): static
    {
        if (!$this->pages->contains($page)) {
            $this->pages->add($page);
            $page->setAudit($this);
        }

        return $this;
    }

    public function removePage(AuditPage $page): static
    {
        if ($this->pages->removeElement($page)) {
            // set the owning side to null (unless already changed)
            if ($page->getAudit() === $this) {
                $page->setAudit(null);
            }
        }

        return $this;
    }

    public function addIssue(AuditIssue $issue): static
    {
        if (!$this->issues->contains($issue)) {
            $this->issues->add($issue);
            $issue->setAudit($this);
        }

        return $this;
    }

    public function removeIssue(AuditIssue $issue): static
    {
        if ($this->issues->removeElement($issue)) {
            // set the owning side to null (unless already changed)
            if ($issue->getAudit() === $this) {
                $issue->setAudit(null);
            }
        }

        return $this;
    }
}
