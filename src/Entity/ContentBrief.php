<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\UuidPrimaryKeyTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'content_briefs')]
class ContentBrief
{
    use UuidPrimaryKeyTrait;

    #[ORM\OneToOne(inversedBy: 'contentBrief')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?TopicResearch $topicResearch = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $targetAudience = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $objective = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $intent = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $toneRecommendation = null;

    #[ORM\Column(nullable: true)]
    private ?int $targetWordCount = null;

    /** @var array<int, array<string, mixed>> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $keyEntities = [];

    /** @var array<int, array<string, mixed>> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $keyQuestions = [];

    /** @var array<string, mixed>|array<int, array<string, mixed>> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $competitorInsights = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $cta = null;

    /** @var array<int, array<string, mixed>> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $sources = [];

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $seoTargets = [];

    /** @var array<int, array<string, mixed>> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $outline = [];

    /** @var array<int, array<string, mixed>> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $faqSuggestions = [];

    /** @var array<int, array<string, mixed>> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $tableSuggestions = [];

    #[ORM\Column(nullable: true)]
    private ?int $estimatedWordCount = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $generatedAt;

    public function __construct()
    {
        $this->initializeUuid();
        $this->generatedAt = new \DateTimeImmutable();
    }

    public function getTopicResearch(): ?TopicResearch
    {
        return $this->topicResearch;
    }

    public function setTopicResearch(?TopicResearch $topicResearch): self
    {
        $this->topicResearch = $topicResearch;
        if (null !== $topicResearch && $topicResearch->getContentBrief() !== $this) {
            $topicResearch->setContentBrief($this);
        }

        return $this;
    }

    public function getTargetAudience(): ?string
    {
        return $this->targetAudience;
    }

    public function setTargetAudience(?string $targetAudience): self
    {
        $this->targetAudience = $this->optionalString($targetAudience);

        return $this;
    }

    public function getObjective(): ?string
    {
        return $this->objective;
    }

    public function setObjective(?string $objective): self
    {
        $this->objective = $this->optionalString($objective);

        return $this;
    }

    public function getIntent(): ?string
    {
        return $this->intent;
    }

    public function setIntent(?string $intent): self
    {
        $this->intent = $this->optionalString($intent, 80);

        return $this;
    }

    public function getToneRecommendation(): ?string
    {
        return $this->toneRecommendation;
    }

    public function setToneRecommendation(?string $toneRecommendation): self
    {
        $this->toneRecommendation = $this->optionalString($toneRecommendation, 120);

        return $this;
    }

    public function getTargetWordCount(): ?int
    {
        return $this->targetWordCount;
    }

    public function setTargetWordCount(?int $targetWordCount): self
    {
        $this->targetWordCount = null === $targetWordCount ? null : max(0, $targetWordCount);

        return $this;
    }

    /** @return array<int, array<string, mixed>> */
    public function getKeyEntities(): array
    {
        return $this->keyEntities;
    }

    /** @param array<int, array<string, mixed>> $keyEntities */
    public function setKeyEntities(array $keyEntities): self
    {
        $this->keyEntities = $keyEntities;

        return $this;
    }

    /** @return array<int, array<string, mixed>> */
    public function getKeyQuestions(): array
    {
        return $this->keyQuestions;
    }

    /** @param array<int, array<string, mixed>> $keyQuestions */
    public function setKeyQuestions(array $keyQuestions): self
    {
        $this->keyQuestions = $keyQuestions;

        return $this;
    }

    /** @return array<string, mixed>|array<int, array<string, mixed>> */
    public function getCompetitorInsights(): array
    {
        return $this->competitorInsights;
    }

    /** @param array<string, mixed>|array<int, array<string, mixed>> $competitorInsights */
    public function setCompetitorInsights(array $competitorInsights): self
    {
        $this->competitorInsights = $competitorInsights;

        return $this;
    }

    public function getCta(): ?string
    {
        return $this->cta;
    }

    public function setCta(?string $cta): self
    {
        $this->cta = $this->optionalString($cta);

        return $this;
    }

    /** @return array<int, array<string, mixed>> */
    public function getSources(): array
    {
        return $this->sources;
    }

    /** @param array<int, array<string, mixed>> $sources */
    public function setSources(array $sources): self
    {
        $this->sources = $sources;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getSeoTargets(): array
    {
        return $this->seoTargets;
    }

    /** @param array<string, mixed> $seoTargets */
    public function setSeoTargets(array $seoTargets): self
    {
        $this->seoTargets = $seoTargets;

        return $this;
    }

    /** @return array<int, array<string, mixed>> */
    public function getOutline(): array
    {
        return $this->outline;
    }

    /** @param array<int, array<string, mixed>> $outline */
    public function setOutline(array $outline): self
    {
        $this->outline = $outline;

        return $this;
    }

    /** @return array<int, array<string, mixed>> */
    public function getFaqSuggestions(): array
    {
        return $this->faqSuggestions;
    }

    /** @param array<int, array<string, mixed>> $faqSuggestions */
    public function setFaqSuggestions(array $faqSuggestions): self
    {
        $this->faqSuggestions = $faqSuggestions;

        return $this;
    }

    /** @return array<int, array<string, mixed>> */
    public function getTableSuggestions(): array
    {
        return $this->tableSuggestions;
    }

    /** @param array<int, array<string, mixed>> $tableSuggestions */
    public function setTableSuggestions(array $tableSuggestions): self
    {
        $this->tableSuggestions = $tableSuggestions;

        return $this;
    }

    public function getEstimatedWordCount(): ?int
    {
        return $this->estimatedWordCount;
    }

    public function setEstimatedWordCount(?int $estimatedWordCount): self
    {
        $this->estimatedWordCount = null === $estimatedWordCount ? null : max(0, $estimatedWordCount);

        return $this;
    }

    public function getGeneratedAt(): \DateTimeImmutable
    {
        return $this->generatedAt;
    }

    public function setGeneratedAt(\DateTimeImmutable $generatedAt): self
    {
        $this->generatedAt = $generatedAt;

        return $this;
    }

    private function optionalString(?string $value, ?int $maxLength = null): ?string
    {
        if (null === $value || '' === trim($value)) {
            return null;
        }

        $value = trim($value);

        return null === $maxLength ? $value : mb_substr($value, 0, $maxLength);
    }
}
