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
use App\Repository\GeoPromptRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    normalizationContext: ['groups' => ['api:read', 'geoprompt:read']],
    denormalizationContext: ['groups' => ['geoprompt:write']],
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Patch(),
        new Delete(),
    ]
)]
#[ORM\Entity(repositoryClass: GeoPromptRepository::class)]
#[ORM\Table(name: 'geo_prompts')]
#[ORM\Index(name: 'idx_geo_prompts_project_active', columns: ['project_id', 'is_active'])]
class GeoPrompt
{
    use TimestampableTrait;
    use UuidPrimaryKeyTrait;

    #[ORM\ManyToOne(inversedBy: 'geoPrompts')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['geoprompt:read', 'geoprompt:write'])]
    private ?Project $project = null;

    #[ORM\ManyToOne(inversedBy: 'geoPrompts')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['geoprompt:read', 'geoprompt:write'])]
    private ?Keyword $keyword = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    #[Groups(['geoprompt:read', 'geoprompt:write'])]
    private string $promptText = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['geoprompt:read', 'geoprompt:write'])]
    private ?string $topic = null;

    #[ORM\Column]
    #[Groups(['geoprompt:read', 'geoprompt:write'])]
    private bool $isActive = true;

    /** @var Collection<int, GeoResult> */
    #[ORM\OneToMany(mappedBy: 'geoPrompt', targetEntity: GeoResult::class, orphanRemoval: true)]
    private Collection $results;

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->results = new ArrayCollection();
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

    public function getKeyword(): ?Keyword
    {
        return $this->keyword;
    }

    public function setKeyword(?Keyword $keyword): self
    {
        $this->keyword = $keyword;

        return $this;
    }

    public function getPromptText(): string
    {
        return $this->promptText;
    }

    public function setPromptText(string $promptText): self
    {
        $this->promptText = $promptText;

        return $this;
    }

    public function getTopic(): ?string
    {
        return $this->topic;
    }

    public function setTopic(?string $topic): static
    {
        $this->topic = $topic;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    /**
     * @return Collection<int, GeoResult>
     */
    public function getResults(): Collection
    {
        return $this->results;
    }

    public function addResult(GeoResult $result): static
    {
        if (!$this->results->contains($result)) {
            $this->results->add($result);
            $result->setGeoPrompt($this);
        }

        return $this;
    }

    public function removeResult(GeoResult $result): static
    {
        if ($this->results->removeElement($result)) {
            // set the owning side to null (unless already changed)
            if ($result->getGeoPrompt() === $this) {
                $result->setGeoPrompt(null);
            }
        }

        return $this;
    }
}
