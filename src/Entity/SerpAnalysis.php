<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'serp_analyses')]
class SerpAnalysis
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\OneToOne(inversedBy: 'serpAnalysis')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?TopicResearch $topicResearch = null;

    /** @var array<int, array<string, mixed>> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $competitors = [];

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $serpFeatures = [];

    /** @var array<int, array<string, mixed>> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $contentGaps = [];

    /** @var array<int, array<string, mixed>> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $questions = [];

    #[ORM\Column]
    private int $averageWordCount = 0;

    #[ORM\Column]
    private int $totalQuestions = 0;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $rawSerpResponse = [];

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $analyzedAt;

    public function __construct()
    {
        $this->id = self::generateUuid();
        $this->analyzedAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTopicResearch(): ?TopicResearch
    {
        return $this->topicResearch;
    }

    public function setTopicResearch(?TopicResearch $topicResearch): self
    {
        $this->topicResearch = $topicResearch;
        if (null !== $topicResearch && $topicResearch->getSerpAnalysis() !== $this) {
            $topicResearch->setSerpAnalysis($this);
        }

        return $this;
    }

    /** @return array<int, array<string, mixed>> */
    public function getCompetitors(): array
    {
        return $this->competitors;
    }

    /** @param array<int, array<string, mixed>> $competitors */
    public function setCompetitors(array $competitors): self
    {
        $this->competitors = $competitors;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getSerpFeatures(): array
    {
        return $this->serpFeatures;
    }

    /** @param array<string, mixed> $serpFeatures */
    public function setSerpFeatures(array $serpFeatures): self
    {
        $this->serpFeatures = $serpFeatures;

        return $this;
    }

    /** @return array<int, array<string, mixed>> */
    public function getContentGaps(): array
    {
        return $this->contentGaps;
    }

    /** @param array<int, array<string, mixed>> $contentGaps */
    public function setContentGaps(array $contentGaps): self
    {
        $this->contentGaps = $contentGaps;

        return $this;
    }

    /** @return array<int, array<string, mixed>> */
    public function getQuestions(): array
    {
        return $this->questions;
    }

    /** @param array<int, array<string, mixed>> $questions */
    public function setQuestions(array $questions): self
    {
        $this->questions = $questions;
        $this->totalQuestions = count($questions);

        return $this;
    }

    public function getAverageWordCount(): int
    {
        return $this->averageWordCount;
    }

    public function setAverageWordCount(int $averageWordCount): self
    {
        $this->averageWordCount = max(0, $averageWordCount);

        return $this;
    }

    public function getTotalQuestions(): int
    {
        return $this->totalQuestions;
    }

    public function setTotalQuestions(int $totalQuestions): self
    {
        $this->totalQuestions = max(0, $totalQuestions);

        return $this;
    }

    /** @return array<string, mixed> */
    public function getRawSerpResponse(): array
    {
        return $this->rawSerpResponse;
    }

    /** @param array<string, mixed> $rawSerpResponse */
    public function setRawSerpResponse(array $rawSerpResponse): self
    {
        $this->rawSerpResponse = $rawSerpResponse;

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

    private static function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0F) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
