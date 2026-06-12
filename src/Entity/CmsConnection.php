<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TimestampableTrait;
use App\Entity\Traits\UuidPrimaryKeyTrait;
use App\Enum\CmsProvider;
use App\Repository\CmsConnectionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CmsConnectionRepository::class)]
#[ORM\Table(name: 'cms_connections')]
#[ORM\Index(name: 'idx_cms_connections_project_provider', columns: ['project_id', 'provider'])]
class CmsConnection
{
    use TimestampableTrait;
    use UuidPrimaryKeyTrait;

    #[ORM\ManyToOne(inversedBy: 'cmsConnections')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\Column(enumType: CmsProvider::class)]
    private CmsProvider $provider = CmsProvider::WORDPRESS;

    #[ORM\Column(length: 500)]
    #[Assert\NotBlank]
    #[Assert\Url]
    #[Assert\Length(max: 500)]
    private string $baseUrl = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $encryptedAccessToken = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $encryptedRefreshToken = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $encryptedApiKey = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['jsonb' => true])]
    private ?array $settings = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $tokenExpiresAt = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastTestedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lastError = null;

    /** @var Collection<int, CmsPublication> */
    #[ORM\OneToMany(mappedBy: 'cmsConnection', targetEntity: CmsPublication::class, orphanRemoval: true)]
    private Collection $publications;

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->publications = new ArrayCollection();
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

    public function getProvider(): CmsProvider
    {
        return $this->provider;
    }

    public function setProvider(CmsProvider $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function setBaseUrl(string $baseUrl): self
    {
        $this->baseUrl = $baseUrl;

        return $this;
    }

    public function getEncryptedApiKey(): ?string
    {
        return $this->encryptedApiKey;
    }

    public function setEncryptedApiKey(?string $encryptedApiKey): self
    {
        $this->encryptedApiKey = $encryptedApiKey;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getSettings(): ?array
    {
        return $this->settings;
    }

    /** @param array<string, mixed>|null $settings */
    public function setSettings(?array $settings): static
    {
        $this->settings = $settings;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getEncryptedAccessToken(): ?string
    {
        return $this->encryptedAccessToken;
    }

    public function setEncryptedAccessToken(?string $encryptedAccessToken): static
    {
        $this->encryptedAccessToken = $encryptedAccessToken;

        return $this;
    }

    public function getEncryptedRefreshToken(): ?string
    {
        return $this->encryptedRefreshToken;
    }

    public function setEncryptedRefreshToken(?string $encryptedRefreshToken): static
    {
        $this->encryptedRefreshToken = $encryptedRefreshToken;

        return $this;
    }

    public function getTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->tokenExpiresAt;
    }

    public function setTokenExpiresAt(?\DateTimeImmutable $tokenExpiresAt): static
    {
        $this->tokenExpiresAt = $tokenExpiresAt;

        return $this;
    }

    public function getLastTestedAt(): ?\DateTimeImmutable
    {
        return $this->lastTestedAt;
    }

    public function setLastTestedAt(?\DateTimeImmutable $lastTestedAt): static
    {
        $this->lastTestedAt = $lastTestedAt;

        return $this;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function setLastError(?string $lastError): static
    {
        $this->lastError = $lastError;

        return $this;
    }

    /**
     * @return Collection<int, CmsPublication>
     */
    public function getPublications(): Collection
    {
        return $this->publications;
    }

    public function addPublication(CmsPublication $publication): static
    {
        if (!$this->publications->contains($publication)) {
            $this->publications->add($publication);
            $publication->setCmsConnection($this);
        }

        return $this;
    }

    public function removePublication(CmsPublication $publication): static
    {
        if ($this->publications->removeElement($publication)) {
            // set the owning side to null (unless already changed)
            if ($publication->getCmsConnection() === $this) {
                $publication->setCmsConnection(null);
            }
        }

        return $this;
    }
}
