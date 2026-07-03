<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\UuidPrimaryKeyTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'intelligence_analyses')]
class IntelligenceAnalysis
{
    use UuidPrimaryKeyTrait;

    #[ORM\OneToOne(inversedBy: 'intelligenceAnalysis')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?TopicResearch $topicResearch = null;

    #[ORM\Column(length: 80)]
    private string $primaryIntent = '';

    /** @var array<string, float|int> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $intentBreakdown = [];

    /** @var array<int, array<string, mixed>> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $entities = [];

    /** @var array<int, array<string, mixed>> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $semanticConcepts = [];

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $analyzedAt;

    public function __construct()
    {
        $this->initializeUuid();
        $this->analyzedAt = new \DateTimeImmutable();
    }

    public function getTopicResearch(): ?TopicResearch
    {
        return $this->topicResearch;
    }

    public function setTopicResearch(?TopicResearch $topicResearch): self
    {
        $this->topicResearch = $topicResearch;
        if (null !== $topicResearch && $topicResearch->getIntelligenceAnalysis() !== $this) {
            $topicResearch->setIntelligenceAnalysis($this);
        }

        return $this;
    }

    public function getPrimaryIntent(): string
    {
        return $this->primaryIntent;
    }

    public function setPrimaryIntent(string $primaryIntent): self
    {
        $this->primaryIntent = mb_substr(trim($primaryIntent), 0, 80);

        return $this;
    }

    /** @return array<string, float|int> */
    public function getIntentBreakdown(): array
    {
        return $this->intentBreakdown;
    }

    /** @param array<string, float|int> $intentBreakdown */
    public function setIntentBreakdown(array $intentBreakdown): self
    {
        $this->intentBreakdown = $intentBreakdown;

        return $this;
    }

    /** @return array<int, array<string, mixed>> */
    public function getEntities(): array
    {
        return $this->entities;
    }

    /** @param array<int, array<string, mixed>> $entities */
    public function setEntities(array $entities): self
    {
        $this->entities = $entities;

        return $this;
    }

    /** @return array<int, array<string, mixed>> */
    public function getSemanticConcepts(): array
    {
        return $this->semanticConcepts;
    }

    /** @param array<int, array<string, mixed>> $semanticConcepts */
    public function setSemanticConcepts(array $semanticConcepts): self
    {
        $this->semanticConcepts = $semanticConcepts;

        return $this;
    }

    public function getAnalyzedAt(): \DateTimeImmutable
    {
        return $this->analyzedAt;
    }

    public function setAnalyzedAt(\DateTimeImmutable $analyzedAt): self
    {
        $this->analyzedAt = $analyzedAt;

        return $this;
    }
}
