<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Traits\UuidPrimaryKeyTrait;
use App\Enum\GeoProvider;
use App\Repository\GeoResultRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    normalizationContext: ['groups' => ['api:read', 'georesult:read']],
    operations: [
        new GetCollection(),
        new Get(),
    ]
)]
#[ORM\Entity(repositoryClass: GeoResultRepository::class)]
#[ORM\Table(name: 'geo_results')]
#[ORM\Index(name: 'idx_geo_results_prompt_checked', columns: ['geo_prompt_id', 'checked_at'])]
class GeoResult
{
    use UuidPrimaryKeyTrait;

    #[ORM\ManyToOne(inversedBy: 'results')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['georesult:read'])]
    private ?GeoPrompt $geoPrompt = null;

    #[ORM\Column(enumType: GeoProvider::class)]
    #[Groups(['georesult:read'])]
    private GeoProvider $provider = GeoProvider::CHATGPT;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['georesult:read'])]
    private ?string $responseText = null;

    #[ORM\Column]
    #[Groups(['georesult:read'])]
    private bool $mentionedBrand = false;

    #[ORM\Column]
    #[Groups(['georesult:read'])]
    private bool $citedProjectUrl = false;

    /** @var array<int, string>|null */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['jsonb' => true])]
    #[Groups(['georesult:read'])]
    private ?array $citedUrlsJson = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['jsonb' => true])]
    #[Groups(['georesult:read'])]
    private ?array $competitorsJson = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    #[Groups(['georesult:read'])]
    private ?string $sentimentScore = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    #[Groups(['georesult:read'])]
    private ?int $visibilityScore = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    #[Groups(['georesult:read'])]
    private \DateTimeImmutable $checkedAt;

    public function __construct()
    {
        $this->initializeUuid();
        $this->checkedAt = new \DateTimeImmutable();
    }

    public function getGeoPrompt(): ?GeoPrompt
    {
        return $this->geoPrompt;
    }

    public function setGeoPrompt(?GeoPrompt $geoPrompt): self
    {
        $this->geoPrompt = $geoPrompt;

        return $this;
    }

    public function getProvider(): GeoProvider
    {
        return $this->provider;
    }

    public function setProvider(GeoProvider $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    public function getResponseText(): ?string
    {
        return $this->responseText;
    }

    public function setResponseText(?string $responseText): static
    {
        $this->responseText = $responseText;

        return $this;
    }

    public function isMentionedBrand(): ?bool
    {
        return $this->mentionedBrand;
    }

    public function setMentionedBrand(bool $mentionedBrand): static
    {
        $this->mentionedBrand = $mentionedBrand;

        return $this;
    }

    public function isCitedProjectUrl(): ?bool
    {
        return $this->citedProjectUrl;
    }

    public function setCitedProjectUrl(bool $citedProjectUrl): static
    {
        $this->citedProjectUrl = $citedProjectUrl;

        return $this;
    }

    public function getCitedUrlsJson(): ?array
    {
        return $this->citedUrlsJson;
    }

    public function setCitedUrlsJson(?array $citedUrlsJson): static
    {
        $this->citedUrlsJson = $citedUrlsJson;

        return $this;
    }

    public function getCompetitorsJson(): ?array
    {
        return $this->competitorsJson;
    }

    public function setCompetitorsJson(?array $competitorsJson): static
    {
        $this->competitorsJson = $competitorsJson;

        return $this;
    }

    public function getSentimentScore(): ?string
    {
        return $this->sentimentScore;
    }

    public function setSentimentScore(?string $sentimentScore): static
    {
        $this->sentimentScore = $sentimentScore;

        return $this;
    }

    public function getVisibilityScore(): ?int
    {
        return $this->visibilityScore;
    }

    public function setVisibilityScore(?int $visibilityScore): static
    {
        $this->visibilityScore = $visibilityScore;

        return $this;
    }

    public function getCheckedAt(): ?\DateTimeImmutable
    {
        return $this->checkedAt;
    }

    public function setCheckedAt(\DateTimeImmutable $checkedAt): static
    {
        $this->checkedAt = $checkedAt;

        return $this;
    }
}
