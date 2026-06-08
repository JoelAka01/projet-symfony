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
use App\Repository\OrganizationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    normalizationContext: ['groups' => ['api:read', 'organization:read']],
    denormalizationContext: ['groups' => ['organization:write']],
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Patch(),
        new Delete(),
    ]
)]
#[ORM\Entity(repositoryClass: OrganizationRepository::class)]
#[ORM\Table(name: 'organizations')]
class Organization
{
    use TimestampableTrait;
    use UuidPrimaryKeyTrait;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    #[Groups(['organization:read', 'organization:write'])]
    private string $name = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Email]
    #[Assert\Length(max: 255)]
    #[Groups(['organization:read', 'organization:write'])]
    private ?string $billingEmail = null;

    #[ORM\Column]
    #[Groups(['organization:read', 'organization:write'])]
    private bool $whiteLabelEnabled = false;

    /** @var Collection<int, OrganizationUser> */
    #[ORM\OneToMany(mappedBy: 'organization', targetEntity: OrganizationUser::class, orphanRemoval: true)]
    private Collection $organizationUsers;

    /** @var Collection<int, Project> */
    #[ORM\OneToMany(mappedBy: 'organization', targetEntity: Project::class, orphanRemoval: true)]
    private Collection $projects;

    /** @var Collection<int, ApiKey> */
    #[ORM\OneToMany(mappedBy: 'organization', targetEntity: ApiKey::class, orphanRemoval: true)]
    private Collection $apiKeys;

    /** @var Collection<int, AuditLog> */
    #[ORM\OneToMany(mappedBy: 'organization', targetEntity: AuditLog::class)]
    private Collection $auditLogs;

    /** @var Collection<int, RateLimitEvent> */
    #[ORM\OneToMany(mappedBy: 'organization', targetEntity: RateLimitEvent::class)]
    private Collection $rateLimitEvents;

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->organizationUsers = new ArrayCollection();
        $this->projects = new ArrayCollection();
        $this->apiKeys = new ArrayCollection();
        $this->auditLogs = new ArrayCollection();
        $this->rateLimitEvents = new ArrayCollection();
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

    public function getBillingEmail(): ?string
    {
        return $this->billingEmail;
    }

    public function setBillingEmail(?string $billingEmail): self
    {
        $this->billingEmail = $billingEmail;

        return $this;
    }

    public function isWhiteLabelEnabled(): bool
    {
        return $this->whiteLabelEnabled;
    }

    public function setWhiteLabelEnabled(bool $whiteLabelEnabled): self
    {
        $this->whiteLabelEnabled = $whiteLabelEnabled;

        return $this;
    }

    /** @return Collection<int, OrganizationUser> */
    public function getOrganizationUsers(): Collection
    {
        return $this->organizationUsers;
    }

    /** @return Collection<int, Project> */
    public function getProjects(): Collection
    {
        return $this->projects;
    }

    public function addOrganizationUser(OrganizationUser $organizationUser): static
    {
        if (!$this->organizationUsers->contains($organizationUser)) {
            $this->organizationUsers->add($organizationUser);
            $organizationUser->setOrganization($this);
        }

        return $this;
    }

    public function removeOrganizationUser(OrganizationUser $organizationUser): static
    {
        if ($this->organizationUsers->removeElement($organizationUser)) {
            // set the owning side to null (unless already changed)
            if ($organizationUser->getOrganization() === $this) {
                $organizationUser->setOrganization(null);
            }
        }

        return $this;
    }

    public function addProject(Project $project): static
    {
        if (!$this->projects->contains($project)) {
            $this->projects->add($project);
            $project->setOrganization($this);
        }

        return $this;
    }

    public function removeProject(Project $project): static
    {
        if ($this->projects->removeElement($project)) {
            // set the owning side to null (unless already changed)
            if ($project->getOrganization() === $this) {
                $project->setOrganization(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ApiKey>
     */
    public function getApiKeys(): Collection
    {
        return $this->apiKeys;
    }

    public function addApiKey(ApiKey $apiKey): static
    {
        if (!$this->apiKeys->contains($apiKey)) {
            $this->apiKeys->add($apiKey);
            $apiKey->setOrganization($this);
        }

        return $this;
    }

    public function removeApiKey(ApiKey $apiKey): static
    {
        if ($this->apiKeys->removeElement($apiKey)) {
            // set the owning side to null (unless already changed)
            if ($apiKey->getOrganization() === $this) {
                $apiKey->setOrganization(null);
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
            $auditLog->setOrganization($this);
        }

        return $this;
    }

    public function removeAuditLog(AuditLog $auditLog): static
    {
        if ($this->auditLogs->removeElement($auditLog)) {
            // set the owning side to null (unless already changed)
            if ($auditLog->getOrganization() === $this) {
                $auditLog->setOrganization(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, RateLimitEvent>
     */
    public function getRateLimitEvents(): Collection
    {
        return $this->rateLimitEvents;
    }

    public function addRateLimitEvent(RateLimitEvent $rateLimitEvent): static
    {
        if (!$this->rateLimitEvents->contains($rateLimitEvent)) {
            $this->rateLimitEvents->add($rateLimitEvent);
            $rateLimitEvent->setOrganization($this);
        }

        return $this;
    }

    public function removeRateLimitEvent(RateLimitEvent $rateLimitEvent): static
    {
        if ($this->rateLimitEvents->removeElement($rateLimitEvent)) {
            // set the owning side to null (unless already changed)
            if ($rateLimitEvent->getOrganization() === $this) {
                $rateLimitEvent->setOrganization(null);
            }
        }

        return $this;
    }
}
