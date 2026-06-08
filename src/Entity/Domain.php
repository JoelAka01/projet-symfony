<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Traits\TimestampableTrait;
use App\Entity\Traits\UuidPrimaryKeyTrait;
use App\Repository\DomainRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    normalizationContext: ['groups' => ['api:read', 'domain:read']],
    denormalizationContext: ['groups' => ['domain:write']],
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Patch(),
        new Delete(),
    ]
)]
#[ORM\Entity(repositoryClass: DomainRepository::class)]
#[ORM\Table(name: 'domains')]
#[ORM\UniqueConstraint(name: 'uniq_domains_project_root', columns: ['project_id', 'root_domain'])]
class Domain
{
    use TimestampableTrait;
    use UuidPrimaryKeyTrait;

    #[ORM\ManyToOne(inversedBy: 'domains')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['domain:read', 'domain:write'])]
    private ?Project $project = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['domain:read', 'domain:write'])]
    private string $rootDomain = '';

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    #[Groups(['domain:read'])]
    private ?\DateTimeImmutable $verifiedAt = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    #[Groups(['domain:read', 'domain:write'])]
    private ?string $verificationMethod = null;

    /** @var Collection<int, Audit> */
    #[ORM\OneToMany(mappedBy: 'domain', targetEntity: Audit::class, orphanRemoval: true)]
    private Collection $audits;

    /** @var Collection<int, BacklinkSite> */
    #[ORM\OneToMany(mappedBy: 'domain', targetEntity: BacklinkSite::class, orphanRemoval: true)]
    private Collection $backlinkSites;

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->audits = new ArrayCollection();
        $this->backlinkSites = new ArrayCollection();
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

    public function getRootDomain(): string
    {
        return $this->rootDomain;
    }

    public function setRootDomain(string $rootDomain): self
    {
        $this->rootDomain = strtolower(trim($rootDomain));

        return $this;
    }

    public function getVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->verifiedAt;
    }

    public function setVerifiedAt(?\DateTimeImmutable $verifiedAt): self
    {
        $this->verifiedAt = $verifiedAt;

        return $this;
    }

    public function getVerificationMethod(): ?string
    {
        return $this->verificationMethod;
    }

    public function setVerificationMethod(?string $verificationMethod): self
    {
        $this->verificationMethod = $verificationMethod;

        return $this;
    }

    /**
     * @return Collection<int, Audit>
     */
    public function getAudits(): Collection
    {
        return $this->audits;
    }

    public function addAudit(Audit $audit): static
    {
        if (!$this->audits->contains($audit)) {
            $this->audits->add($audit);
            $audit->setDomain($this);
        }

        return $this;
    }

    public function removeAudit(Audit $audit): static
    {
        if ($this->audits->removeElement($audit)) {
            // set the owning side to null (unless already changed)
            if ($audit->getDomain() === $this) {
                $audit->setDomain(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, BacklinkSite>
     */
    public function getBacklinkSites(): Collection
    {
        return $this->backlinkSites;
    }

    public function addBacklinkSite(BacklinkSite $backlinkSite): static
    {
        if (!$this->backlinkSites->contains($backlinkSite)) {
            $this->backlinkSites->add($backlinkSite);
            $backlinkSite->setDomain($this);
        }

        return $this;
    }

    public function removeBacklinkSite(BacklinkSite $backlinkSite): static
    {
        if ($this->backlinkSites->removeElement($backlinkSite)) {
            // set the owning side to null (unless already changed)
            if ($backlinkSite->getDomain() === $this) {
                $backlinkSite->setDomain(null);
            }
        }

        return $this;
    }
}
