<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TimestampableTrait;
use App\Entity\Traits\UuidPrimaryKeyTrait;
use App\Repository\BacklinkSiteRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BacklinkSiteRepository::class)]
#[ORM\Table(name: 'backlink_sites')]
class BacklinkSite
{
    use TimestampableTrait;
    use UuidPrimaryKeyTrait;

    #[ORM\ManyToOne(inversedBy: 'backlinkSites')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\ManyToOne(inversedBy: 'backlinkSites')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Domain $domain = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $niche = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    private ?int $domainAuthority = null;

    #[ORM\Column(nullable: true)]
    private ?int $trafficEstimate = null;

    #[ORM\Column]
    private bool $acceptsExchanges = true;

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeTimestamps();
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

    public function getNiche(): ?string
    {
        return $this->niche;
    }

    public function setNiche(?string $niche): static
    {
        $this->niche = $niche;

        return $this;
    }

    public function getDomainAuthority(): ?int
    {
        return $this->domainAuthority;
    }

    public function setDomainAuthority(?int $domainAuthority): static
    {
        $this->domainAuthority = $domainAuthority;

        return $this;
    }

    public function getTrafficEstimate(): ?int
    {
        return $this->trafficEstimate;
    }

    public function setTrafficEstimate(?int $trafficEstimate): static
    {
        $this->trafficEstimate = $trafficEstimate;

        return $this;
    }

    public function isAcceptsExchanges(): ?bool
    {
        return $this->acceptsExchanges;
    }

    public function setAcceptsExchanges(bool $acceptsExchanges): static
    {
        $this->acceptsExchanges = $acceptsExchanges;

        return $this;
    }
}
