<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use App\Entity\Traits\TimestampableTrait;
use App\Entity\Traits\UuidPrimaryKeyTrait;
use App\Enum\KeywordSuggestionSource;
use App\Repository\KeywordSuggestionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    normalizationContext: ['groups' => ['api:read', 'keyword_suggestion:read']],
    denormalizationContext: ['groups' => ['keyword_suggestion:write']],
    operations: [
        new GetCollection(),
        new Get(),
        new Patch(),
    ],
)]
#[ORM\Entity(repositoryClass: KeywordSuggestionRepository::class)]
#[ORM\Table(name: 'keyword_suggestions')]
#[ORM\UniqueConstraint(name: 'uniq_keyword_suggestions_project_normalized', columns: ['project_id', 'normalized_term'])]
#[ORM\Index(name: 'idx_keyword_suggestions_project_score', columns: ['project_id', 'opportunity_score'])]
#[ORM\Index(name: 'idx_keyword_suggestions_source', columns: ['source'])]
class KeywordSuggestion
{
    use TimestampableTrait;
    use UuidPrimaryKeyTrait;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['keyword_suggestion:read'])]
    private ?Project $project = null;

    #[ORM\Column(length: 500)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 500)]
    #[Groups(['keyword_suggestion:read'])]
    private string $term = '';

    #[ORM\Column(length: 500)]
    private string $normalizedTerm = '';

    #[ORM\Column(enumType: KeywordSuggestionSource::class)]
    #[Groups(['keyword_suggestion:read'])]
    private KeywordSuggestionSource $source = KeywordSuggestionSource::AUDIT_DETECTED_KEYWORD;

    #[ORM\Column(length: 80, nullable: true)]
    #[Assert\Length(max: 80)]
    #[Groups(['keyword_suggestion:read'])]
    private ?string $intent = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    #[Groups(['keyword_suggestion:read'])]
    private ?string $clusterName = null;

    #[ORM\Column(type: 'smallint')]
    #[Assert\Range(min: 0, max: 100)]
    #[Groups(['keyword_suggestion:read'])]
    private int $opportunityScore = 0;

    #[ORM\Column(type: 'smallint')]
    #[Assert\Range(min: 0, max: 100)]
    #[Groups(['keyword_suggestion:read'])]
    private int $businessScore = 0;

    #[ORM\Column(type: 'smallint')]
    #[Assert\Range(min: 0, max: 100)]
    #[Groups(['keyword_suggestion:read'])]
    private int $difficultyEstimate = 0;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    #[Groups(['keyword_suggestion:read'])]
    private ?int $searchVolumeEstimate = null;

    #[ORM\Column]
    #[Groups(['keyword_suggestion:read', 'keyword_suggestion:write'])]
    private bool $isSelected = false;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $rawData = [];

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

    public function getTerm(): string
    {
        return $this->term;
    }

    public function setTerm(string $term): self
    {
        $this->term = $term;

        return $this;
    }

    public function getNormalizedTerm(): string
    {
        return $this->normalizedTerm;
    }

    public function setNormalizedTerm(string $normalizedTerm): self
    {
        $this->normalizedTerm = $normalizedTerm;

        return $this;
    }

    public function getSource(): KeywordSuggestionSource
    {
        return $this->source;
    }

    public function setSource(KeywordSuggestionSource $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function getIntent(): ?string
    {
        return $this->intent;
    }

    public function setIntent(?string $intent): self
    {
        $this->intent = $intent;

        return $this;
    }

    public function getClusterName(): ?string
    {
        return $this->clusterName;
    }

    public function setClusterName(?string $clusterName): self
    {
        $this->clusterName = $clusterName;

        return $this;
    }

    public function getOpportunityScore(): int
    {
        return $this->opportunityScore;
    }

    public function setOpportunityScore(int $opportunityScore): self
    {
        $this->opportunityScore = max(0, min(100, $opportunityScore));

        return $this;
    }

    public function getBusinessScore(): int
    {
        return $this->businessScore;
    }

    public function setBusinessScore(int $businessScore): self
    {
        $this->businessScore = max(0, min(100, $businessScore));

        return $this;
    }

    public function getDifficultyEstimate(): int
    {
        return $this->difficultyEstimate;
    }

    public function setDifficultyEstimate(int $difficultyEstimate): self
    {
        $this->difficultyEstimate = max(0, min(100, $difficultyEstimate));

        return $this;
    }

    public function getSearchVolumeEstimate(): ?int
    {
        return $this->searchVolumeEstimate;
    }

    public function setSearchVolumeEstimate(?int $searchVolumeEstimate): self
    {
        $this->searchVolumeEstimate = $searchVolumeEstimate;

        return $this;
    }

    public function isSelected(): bool
    {
        return $this->isSelected;
    }

    public function setIsSelected(bool $isSelected): self
    {
        $this->isSelected = $isSelected;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getRawData(): array
    {
        return $this->rawData;
    }

    /** @param array<string, mixed> $rawData */
    public function setRawData(array $rawData): self
    {
        $this->rawData = $rawData;

        return $this;
    }
}
