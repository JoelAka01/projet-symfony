<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\UuidPrimaryKeyTrait;
use App\Repository\AuditPageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AuditPageRepository::class)]
#[ORM\Table(name: 'audit_pages')]
#[ORM\Index(name: 'idx_audit_pages_audit_status', columns: ['audit_id', 'status_code'])]
#[ORM\Index(name: 'idx_audit_pages_audit_normalized_url', columns: ['audit_id', 'normalized_url'])]
#[ORM\Index(name: 'idx_audit_pages_content_hash', columns: ['content_hash'])]
class AuditPage
{
    use UuidPrimaryKeyTrait;

    #[ORM\ManyToOne(inversedBy: 'pages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Audit $audit = null;

    #[ORM\Column(length: 1000)]
    #[Assert\NotBlank]
    #[Assert\Url]
    #[Assert\Length(max: 1000)]
    private string $url = '';

    #[ORM\Column(length: 1000, nullable: true)]
    #[Assert\Url]
    #[Assert\Length(max: 1000)]
    private ?string $normalizedUrl = null;

    #[ORM\Column(length: 1000, nullable: true)]
    #[Assert\Url]
    #[Assert\Length(max: 1000)]
    private ?string $canonicalUrl = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $statusCode = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $contentType = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $metaDescription = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $h1 = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $robotsMeta = null;

    #[ORM\Column(nullable: true)]
    private ?int $wordCount = null;

    #[ORM\Column(nullable: true)]
    private ?int $internalLinksCount = null;

    #[ORM\Column(nullable: true)]
    private ?int $externalLinksCount = null;

    #[ORM\Column(nullable: true)]
    private ?int $imagesWithoutAltCount = null;

    #[ORM\Column(nullable: true)]
    private ?int $loadTimeMs = null;

    #[ORM\Column]
    private bool $isIndexable = true;

    #[ORM\Column]
    private bool $structuredDataPresent = false;

    #[ORM\Column]
    private bool $isOrphan = false;

    #[ORM\Column(length: 64, nullable: true)]
    #[Assert\Length(max: 64)]
    private ?string $contentHash = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    /** @var Collection<int, AuditIssue> */
    #[ORM\OneToMany(mappedBy: 'auditPage', targetEntity: AuditIssue::class)]
    private Collection $issues;

    public function __construct()
    {
        $this->initializeUuid();
        $this->createdAt = new \DateTimeImmutable();
        $this->issues = new ArrayCollection();
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

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getNormalizedUrl(): ?string
    {
        return $this->normalizedUrl;
    }

    public function setNormalizedUrl(?string $normalizedUrl): static
    {
        $this->normalizedUrl = $normalizedUrl;

        return $this;
    }

    public function getCanonicalUrl(): ?string
    {
        return $this->canonicalUrl;
    }

    public function setCanonicalUrl(?string $canonicalUrl): static
    {
        $this->canonicalUrl = $canonicalUrl;

        return $this;
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function setStatusCode(?int $statusCode): static
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    public function getContentType(): ?string
    {
        return $this->contentType;
    }

    public function setContentType(?string $contentType): static
    {
        $this->contentType = null === $contentType ? null : substr($contentType, 0, 255);

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): static
    {
        $this->metaDescription = $metaDescription;

        return $this;
    }

    public function getH1(): ?string
    {
        return $this->h1;
    }

    public function setH1(?string $h1): static
    {
        $this->h1 = $h1;

        return $this;
    }

    public function getRobotsMeta(): ?string
    {
        return $this->robotsMeta;
    }

    public function setRobotsMeta(?string $robotsMeta): static
    {
        $this->robotsMeta = $robotsMeta;

        return $this;
    }

    public function getWordCount(): ?int
    {
        return $this->wordCount;
    }

    public function setWordCount(?int $wordCount): static
    {
        $this->wordCount = $wordCount;

        return $this;
    }

    public function getInternalLinksCount(): ?int
    {
        return $this->internalLinksCount;
    }

    public function setInternalLinksCount(?int $internalLinksCount): static
    {
        $this->internalLinksCount = $internalLinksCount;

        return $this;
    }

    public function getExternalLinksCount(): ?int
    {
        return $this->externalLinksCount;
    }

    public function setExternalLinksCount(?int $externalLinksCount): static
    {
        $this->externalLinksCount = $externalLinksCount;

        return $this;
    }

    public function getImagesWithoutAltCount(): ?int
    {
        return $this->imagesWithoutAltCount;
    }

    public function setImagesWithoutAltCount(?int $imagesWithoutAltCount): static
    {
        $this->imagesWithoutAltCount = $imagesWithoutAltCount;

        return $this;
    }

    public function getLoadTimeMs(): ?int
    {
        return $this->loadTimeMs;
    }

    public function setLoadTimeMs(?int $loadTimeMs): static
    {
        $this->loadTimeMs = $loadTimeMs;

        return $this;
    }

    public function isIndexable(): ?bool
    {
        return $this->isIndexable;
    }

    public function setIsIndexable(bool $isIndexable): static
    {
        $this->isIndexable = $isIndexable;

        return $this;
    }

    public function hasStructuredData(): bool
    {
        return $this->structuredDataPresent;
    }

    public function setStructuredDataPresent(bool $structuredDataPresent): static
    {
        $this->structuredDataPresent = $structuredDataPresent;

        return $this;
    }

    public function isOrphan(): ?bool
    {
        return $this->isOrphan;
    }

    public function setIsOrphan(bool $isOrphan): static
    {
        $this->isOrphan = $isOrphan;

        return $this;
    }

    public function getContentHash(): ?string
    {
        return $this->contentHash;
    }

    public function setContentHash(?string $contentHash): static
    {
        $this->contentHash = $contentHash;

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

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }

    /**
     * @return Collection<int, AuditIssue>
     */
    public function getIssues(): Collection
    {
        return $this->issues;
    }

    public function addIssue(AuditIssue $issue): static
    {
        if (!$this->issues->contains($issue)) {
            $this->issues->add($issue);
            $issue->setAuditPage($this);
        }

        return $this;
    }

    public function removeIssue(AuditIssue $issue): static
    {
        if ($this->issues->removeElement($issue)) {
            // set the owning side to null (unless already changed)
            if ($issue->getAuditPage() === $this) {
                $issue->setAuditPage(null);
            }
        }

        return $this;
    }
}
