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
use App\Enum\ProjectStatus;
use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    normalizationContext: ['groups' => ['api:read', 'project:read']],
    denormalizationContext: ['groups' => ['project:write']],
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Patch(),
        new Delete(),
    ],
)]
#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ORM\Table(name: 'projects')]
#[ORM\Index(name: 'idx_projects_status', columns: ['status'])]
#[ORM\UniqueConstraint(name: 'uniq_projects_owner_name', columns: ['owner_id', 'name'])]
#[UniqueEntity(
    fields: ['owner', 'name'],
    message: 'You already have a project with this name.',
    errorPath: 'name',
)]
class Project
{
    use TimestampableTrait;
    use UuidPrimaryKeyTrait;

    #[ORM\ManyToOne(inversedBy: 'projects')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['project:read', 'project:write'])]
    private ?Organization $organization = null;

    #[ORM\ManyToOne(inversedBy: 'ownedProjects')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['project:read', 'project:write'])]
    private ?User $owner = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    #[Groups(['project:read', 'project:write'])]
    private string $name = '';

    #[ORM\Column(enumType: ProjectStatus::class)]
    #[Groups(['project:read', 'project:write'])]
    private ProjectStatus $status = ProjectStatus::ACTIVE;

    #[ORM\Column(length: 10, nullable: true)]
    #[Assert\Length(max: 10)]
    #[Groups(['project:read', 'project:write'])]
    private ?string $defaultLanguage = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[Assert\Length(max: 10)]
    #[Groups(['project:read', 'project:write'])]
    private ?string $targetCountry = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    #[Groups(['project:read'])]
    private ?int $seoScore = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    #[Groups(['project:read'])]
    private ?int $geoScore = null;



    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'guestProjects')]
    #[ORM\JoinTable(name: 'project_guests')]
    private Collection $guests;

    /** @var Collection<int, ProjectInvitation> */
    #[ORM\OneToMany(mappedBy: 'project', targetEntity: ProjectInvitation::class, orphanRemoval: true)]
    private Collection $invitations;

    /** @var Collection<int, Domain> */
    #[ORM\OneToMany(mappedBy: 'project', targetEntity: Domain::class, orphanRemoval: true)]
    private Collection $domains;

    /** @var Collection<int, CmsConnection> */
    #[ORM\OneToMany(mappedBy: 'project', targetEntity: CmsConnection::class, orphanRemoval: true)]
    private Collection $cmsConnections;

    /** @var Collection<int, Audit> */
    #[ORM\OneToMany(mappedBy: 'project', targetEntity: Audit::class, orphanRemoval: true)]
    private Collection $audits;

    /** @var Collection<int, KeywordCluster> */
    #[ORM\OneToMany(mappedBy: 'project', targetEntity: KeywordCluster::class, orphanRemoval: true)]
    private Collection $keywordClusters;

    /** @var Collection<int, Keyword> */
    #[ORM\OneToMany(mappedBy: 'project', targetEntity: Keyword::class, orphanRemoval: true)]
    private Collection $keywords;

    /** @var Collection<int, KeywordRanking> */
    #[ORM\OneToMany(mappedBy: 'project', targetEntity: KeywordRanking::class, orphanRemoval: true)]
    private Collection $keywordRankings;

    /** @var Collection<int, ContentItem> */
    #[ORM\OneToMany(mappedBy: 'project', targetEntity: ContentItem::class, orphanRemoval: true)]
    private Collection $contentItems;

    /** @var Collection<int, GeoPrompt> */
    #[ORM\OneToMany(mappedBy: 'project', targetEntity: GeoPrompt::class, orphanRemoval: true)]
    private Collection $geoPrompts;

    /** @var Collection<int, GeoDailySnapshot> */
    #[ORM\OneToMany(mappedBy: 'project', targetEntity: GeoDailySnapshot::class, orphanRemoval: true)]
    private Collection $geoDailySnapshots;

    /** @var Collection<int, BacklinkSite> */
    #[ORM\OneToMany(mappedBy: 'project', targetEntity: BacklinkSite::class, orphanRemoval: true)]
    private Collection $backlinkSites;

    /** @var Collection<int, Backlink> */
    #[ORM\OneToMany(mappedBy: 'sourceProject', targetEntity: Backlink::class)]
    private Collection $sourceBacklinks;

    /** @var Collection<int, Backlink> */
    #[ORM\OneToMany(mappedBy: 'targetProject', targetEntity: Backlink::class)]
    private Collection $targetBacklinks;

    /** @var Collection<int, BacklinkExchange> */
    #[ORM\OneToMany(mappedBy: 'requesterProject', targetEntity: BacklinkExchange::class)]
    private Collection $requesterBacklinkExchanges;

    /** @var Collection<int, BacklinkExchange> */
    #[ORM\OneToMany(mappedBy: 'publisherProject', targetEntity: BacklinkExchange::class)]
    private Collection $publisherBacklinkExchanges;

    /** @var Collection<int, AnalyticsDailySnapshot> */
    #[ORM\OneToMany(mappedBy: 'project', targetEntity: AnalyticsDailySnapshot::class, orphanRemoval: true)]
    private Collection $analyticsDailySnapshots;

    /** @var Collection<int, AuditLog> */
    #[ORM\OneToMany(mappedBy: 'project', targetEntity: AuditLog::class)]
    private Collection $auditLogs;

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->domains = new ArrayCollection();
        $this->cmsConnections = new ArrayCollection();
        $this->audits = new ArrayCollection();
        $this->keywordClusters = new ArrayCollection();
        $this->keywords = new ArrayCollection();
        $this->keywordRankings = new ArrayCollection();
        $this->contentItems = new ArrayCollection();
        $this->geoPrompts = new ArrayCollection();
        $this->geoDailySnapshots = new ArrayCollection();
        $this->backlinkSites = new ArrayCollection();
        $this->sourceBacklinks = new ArrayCollection();
        $this->targetBacklinks = new ArrayCollection();
        $this->requesterBacklinkExchanges = new ArrayCollection();
        $this->publisherBacklinkExchanges = new ArrayCollection();
        $this->analyticsDailySnapshots = new ArrayCollection();
        $this->auditLogs = new ArrayCollection();
        $this->guests = new ArrayCollection();
        $this->invitations = new ArrayCollection();
    }

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    public function setOrganization(?Organization $organization): self
    {
        $this->organization = $organization;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): self
    {
        $this->owner = $owner;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getStatus(): ProjectStatus
    {
        return $this->status;
    }

    public function setStatus(ProjectStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getDefaultLanguage(): ?string
    {
        return $this->defaultLanguage;
    }

    public function setDefaultLanguage(?string $defaultLanguage): self
    {
        $this->defaultLanguage = $defaultLanguage;

        return $this;
    }

    public function getTargetCountry(): ?string
    {
        return $this->targetCountry;
    }

    public function setTargetCountry(?string $targetCountry): self
    {
        $this->targetCountry = $targetCountry;

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

    public function getGeoScore(): ?int
    {
        return $this->geoScore;
    }

    public function setGeoScore(?int $geoScore): self
    {
        $this->geoScore = $geoScore;

        return $this;
    }



    /** @return Collection<int, Domain> */
    public function getDomains(): Collection
    {
        return $this->domains;
    }

    public function addDomain(Domain $domain): static
    {
        if (!$this->domains->contains($domain)) {
            $this->domains->add($domain);
            $domain->setProject($this);
        }

        return $this;
    }

    public function removeDomain(Domain $domain): static
    {
        if ($this->domains->removeElement($domain)) {
            // set the owning side to null (unless already changed)
            if ($domain->getProject() === $this) {
                $domain->setProject(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, CmsConnection>
     */
    public function getCmsConnections(): Collection
    {
        return $this->cmsConnections;
    }

    public function addCmsConnection(CmsConnection $cmsConnection): static
    {
        if (!$this->cmsConnections->contains($cmsConnection)) {
            $this->cmsConnections->add($cmsConnection);
            $cmsConnection->setProject($this);
        }

        return $this;
    }

    public function removeCmsConnection(CmsConnection $cmsConnection): static
    {
        if ($this->cmsConnections->removeElement($cmsConnection)) {
            // set the owning side to null (unless already changed)
            if ($cmsConnection->getProject() === $this) {
                $cmsConnection->setProject(null);
            }
        }

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
            $audit->setProject($this);
        }

        return $this;
    }

    public function removeAudit(Audit $audit): static
    {
        if ($this->audits->removeElement($audit)) {
            // set the owning side to null (unless already changed)
            if ($audit->getProject() === $this) {
                $audit->setProject(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, KeywordCluster>
     */
    public function getKeywordClusters(): Collection
    {
        return $this->keywordClusters;
    }

    public function addKeywordCluster(KeywordCluster $keywordCluster): static
    {
        if (!$this->keywordClusters->contains($keywordCluster)) {
            $this->keywordClusters->add($keywordCluster);
            $keywordCluster->setProject($this);
        }

        return $this;
    }

    public function removeKeywordCluster(KeywordCluster $keywordCluster): static
    {
        if ($this->keywordClusters->removeElement($keywordCluster)) {
            // set the owning side to null (unless already changed)
            if ($keywordCluster->getProject() === $this) {
                $keywordCluster->setProject(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Keyword>
     */
    public function getKeywords(): Collection
    {
        return $this->keywords;
    }

    public function addKeyword(Keyword $keyword): static
    {
        if (!$this->keywords->contains($keyword)) {
            $this->keywords->add($keyword);
            $keyword->setProject($this);
        }

        return $this;
    }

    public function removeKeyword(Keyword $keyword): static
    {
        if ($this->keywords->removeElement($keyword)) {
            // set the owning side to null (unless already changed)
            if ($keyword->getProject() === $this) {
                $keyword->setProject(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, KeywordRanking>
     */
    public function getKeywordRankings(): Collection
    {
        return $this->keywordRankings;
    }

    public function addKeywordRanking(KeywordRanking $keywordRanking): static
    {
        if (!$this->keywordRankings->contains($keywordRanking)) {
            $this->keywordRankings->add($keywordRanking);
            $keywordRanking->setProject($this);
        }

        return $this;
    }

    public function removeKeywordRanking(KeywordRanking $keywordRanking): static
    {
        if ($this->keywordRankings->removeElement($keywordRanking)) {
            // set the owning side to null (unless already changed)
            if ($keywordRanking->getProject() === $this) {
                $keywordRanking->setProject(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ContentItem>
     */
    public function getContentItems(): Collection
    {
        return $this->contentItems;
    }

    public function addContentItem(ContentItem $contentItem): static
    {
        if (!$this->contentItems->contains($contentItem)) {
            $this->contentItems->add($contentItem);
            $contentItem->setProject($this);
        }

        return $this;
    }

    public function removeContentItem(ContentItem $contentItem): static
    {
        if ($this->contentItems->removeElement($contentItem)) {
            // set the owning side to null (unless already changed)
            if ($contentItem->getProject() === $this) {
                $contentItem->setProject(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, GeoPrompt>
     */
    public function getGeoPrompts(): Collection
    {
        return $this->geoPrompts;
    }

    public function addGeoPrompt(GeoPrompt $geoPrompt): static
    {
        if (!$this->geoPrompts->contains($geoPrompt)) {
            $this->geoPrompts->add($geoPrompt);
            $geoPrompt->setProject($this);
        }

        return $this;
    }

    public function removeGeoPrompt(GeoPrompt $geoPrompt): static
    {
        if ($this->geoPrompts->removeElement($geoPrompt)) {
            // set the owning side to null (unless already changed)
            if ($geoPrompt->getProject() === $this) {
                $geoPrompt->setProject(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, GeoDailySnapshot>
     */
    public function getGeoDailySnapshots(): Collection
    {
        return $this->geoDailySnapshots;
    }

    public function addGeoDailySnapshot(GeoDailySnapshot $geoDailySnapshot): static
    {
        if (!$this->geoDailySnapshots->contains($geoDailySnapshot)) {
            $this->geoDailySnapshots->add($geoDailySnapshot);
            $geoDailySnapshot->setProject($this);
        }

        return $this;
    }

    public function removeGeoDailySnapshot(GeoDailySnapshot $geoDailySnapshot): static
    {
        if ($this->geoDailySnapshots->removeElement($geoDailySnapshot)) {
            // set the owning side to null (unless already changed)
            if ($geoDailySnapshot->getProject() === $this) {
                $geoDailySnapshot->setProject(null);
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
            $backlinkSite->setProject($this);
        }

        return $this;
    }

    public function removeBacklinkSite(BacklinkSite $backlinkSite): static
    {
        if ($this->backlinkSites->removeElement($backlinkSite)) {
            // set the owning side to null (unless already changed)
            if ($backlinkSite->getProject() === $this) {
                $backlinkSite->setProject(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Backlink>
     */
    public function getSourceBacklinks(): Collection
    {
        return $this->sourceBacklinks;
    }

    public function addSourceBacklink(Backlink $sourceBacklink): static
    {
        if (!$this->sourceBacklinks->contains($sourceBacklink)) {
            $this->sourceBacklinks->add($sourceBacklink);
            $sourceBacklink->setSourceProject($this);
        }

        return $this;
    }

    public function removeSourceBacklink(Backlink $sourceBacklink): static
    {
        if ($this->sourceBacklinks->removeElement($sourceBacklink)) {
            // set the owning side to null (unless already changed)
            if ($sourceBacklink->getSourceProject() === $this) {
                $sourceBacklink->setSourceProject(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Backlink>
     */
    public function getTargetBacklinks(): Collection
    {
        return $this->targetBacklinks;
    }

    public function addTargetBacklink(Backlink $targetBacklink): static
    {
        if (!$this->targetBacklinks->contains($targetBacklink)) {
            $this->targetBacklinks->add($targetBacklink);
            $targetBacklink->setTargetProject($this);
        }

        return $this;
    }

    public function removeTargetBacklink(Backlink $targetBacklink): static
    {
        if ($this->targetBacklinks->removeElement($targetBacklink)) {
            // set the owning side to null (unless already changed)
            if ($targetBacklink->getTargetProject() === $this) {
                $targetBacklink->setTargetProject(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, BacklinkExchange>
     */
    public function getRequesterBacklinkExchanges(): Collection
    {
        return $this->requesterBacklinkExchanges;
    }

    public function addRequesterBacklinkExchange(BacklinkExchange $requesterBacklinkExchange): static
    {
        if (!$this->requesterBacklinkExchanges->contains($requesterBacklinkExchange)) {
            $this->requesterBacklinkExchanges->add($requesterBacklinkExchange);
            $requesterBacklinkExchange->setRequesterProject($this);
        }

        return $this;
    }

    public function removeRequesterBacklinkExchange(BacklinkExchange $requesterBacklinkExchange): static
    {
        if ($this->requesterBacklinkExchanges->removeElement($requesterBacklinkExchange)) {
            // set the owning side to null (unless already changed)
            if ($requesterBacklinkExchange->getRequesterProject() === $this) {
                $requesterBacklinkExchange->setRequesterProject(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, BacklinkExchange>
     */
    public function getPublisherBacklinkExchanges(): Collection
    {
        return $this->publisherBacklinkExchanges;
    }

    public function addPublisherBacklinkExchange(BacklinkExchange $publisherBacklinkExchange): static
    {
        if (!$this->publisherBacklinkExchanges->contains($publisherBacklinkExchange)) {
            $this->publisherBacklinkExchanges->add($publisherBacklinkExchange);
            $publisherBacklinkExchange->setPublisherProject($this);
        }

        return $this;
    }

    public function removePublisherBacklinkExchange(BacklinkExchange $publisherBacklinkExchange): static
    {
        if ($this->publisherBacklinkExchanges->removeElement($publisherBacklinkExchange)) {
            // set the owning side to null (unless already changed)
            if ($publisherBacklinkExchange->getPublisherProject() === $this) {
                $publisherBacklinkExchange->setPublisherProject(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, AnalyticsDailySnapshot>
     */
    public function getAnalyticsDailySnapshots(): Collection
    {
        return $this->analyticsDailySnapshots;
    }

    public function addAnalyticsDailySnapshot(AnalyticsDailySnapshot $analyticsDailySnapshot): static
    {
        if (!$this->analyticsDailySnapshots->contains($analyticsDailySnapshot)) {
            $this->analyticsDailySnapshots->add($analyticsDailySnapshot);
            $analyticsDailySnapshot->setProject($this);
        }

        return $this;
    }

    public function removeAnalyticsDailySnapshot(AnalyticsDailySnapshot $analyticsDailySnapshot): static
    {
        if ($this->analyticsDailySnapshots->removeElement($analyticsDailySnapshot)) {
            // set the owning side to null (unless already changed)
            if ($analyticsDailySnapshot->getProject() === $this) {
                $analyticsDailySnapshot->setProject(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, AuditLog>
     */
    public function getAuditLogs(): Collection
    {
        return $this->auditLogs;
    }

    public function addAuditLog(AuditLog $auditLog): static
    {
        if (!$this->auditLogs->contains($auditLog)) {
            $this->auditLogs->add($auditLog);
            $auditLog->setProject($this);
        }

        return $this;
    }

    public function removeAuditLog(AuditLog $auditLog): static
    {
        if ($this->auditLogs->removeElement($auditLog)) {
            // set the owning side to null (unless already changed)
            if ($auditLog->getProject() === $this) {
                $auditLog->setProject(null);
            }
        }

        return $this;
    }

    /** @return Collection<int, User> */
    public function getGuests(): Collection
    {
        return $this->guests;
    }

    public function addGuest(User $user): self
    {
        if (!$this->guests->contains($user)) {
            $this->guests->add($user);
        }

        return $this;
    }

    public function removeGuest(User $user): self
    {
        $this->guests->removeElement($user);

        return $this;
    }

    /** @return Collection<int, ProjectInvitation> */
    public function getInvitations(): Collection
    {
        return $this->invitations;
    }

    public function addInvitation(ProjectInvitation $invitation): self
    {
        if (!$this->invitations->contains($invitation)) {
            $this->invitations->add($invitation);
            $invitation->setProject($this);
        }

        return $this;
    }

    public function removeInvitation(ProjectInvitation $invitation): self
    {
        if ($this->invitations->removeElement($invitation)) {
            if ($invitation->getProject() === $this) {
                $invitation->setProject(null);
            }
        }

        return $this;
    }
}
