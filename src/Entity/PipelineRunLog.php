<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\UuidPrimaryKeyTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'pipeline_run_logs')]
#[ORM\Index(name: 'idx_pipeline_run_logs_topic_step', columns: ['topic_research_id', 'step'])]
#[ORM\Index(name: 'idx_pipeline_run_logs_created', columns: ['created_at'])]
class PipelineRunLog
{
    use UuidPrimaryKeyTrait;

    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_RETRIED = 'retried';

    #[ORM\ManyToOne(inversedBy: 'runLogs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?TopicResearch $topicResearch = null;

    #[ORM\Column(length: 40)]
    private string $step = '';

    #[ORM\Column]
    private int $attempt = 1;

    #[ORM\Column(type: Types::TEXT)]
    private string $promptSent = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $rawResponse = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['jsonb' => true])]
    private ?array $parsedResponse = null;

    #[ORM\Column(length: 120)]
    private string $model = '';

    #[ORM\Column(length: 50)]
    private string $provider = '';

    #[ORM\Column]
    private int $inputTokens = 0;

    #[ORM\Column]
    private int $outputTokens = 0;

    #[ORM\Column]
    private int $totalCredits = 0;

    #[ORM\Column]
    private int $durationMs = 0;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_SUCCESS;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->initializeUuid();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getTopicResearch(): ?TopicResearch
    {
        return $this->topicResearch;
    }

    public function setTopicResearch(?TopicResearch $topicResearch): self
    {
        $this->topicResearch = $topicResearch;

        return $this;
    }

    public function getStep(): string
    {
        return $this->step;
    }

    public function setStep(string $step): self
    {
        $this->step = mb_substr(trim($step), 0, 40);

        return $this;
    }

    public function getAttempt(): int
    {
        return $this->attempt;
    }

    public function setAttempt(int $attempt): self
    {
        $this->attempt = max(1, $attempt);

        return $this;
    }

    public function getPromptSent(): string
    {
        return $this->promptSent;
    }

    public function setPromptSent(string $promptSent): self
    {
        $this->promptSent = $promptSent;

        return $this;
    }

    public function getRawResponse(): ?string
    {
        return $this->rawResponse;
    }

    public function setRawResponse(?string $rawResponse): self
    {
        $this->rawResponse = $rawResponse;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getParsedResponse(): ?array
    {
        return $this->parsedResponse;
    }

    /** @param array<string, mixed>|null $parsedResponse */
    public function setParsedResponse(?array $parsedResponse): self
    {
        $this->parsedResponse = $parsedResponse;

        return $this;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function setModel(string $model): self
    {
        $this->model = mb_substr(trim($model), 0, 120);

        return $this;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): self
    {
        $this->provider = mb_substr(trim($provider), 0, 50);

        return $this;
    }

    public function getInputTokens(): int
    {
        return $this->inputTokens;
    }

    public function setInputTokens(int $inputTokens): self
    {
        $this->inputTokens = max(0, $inputTokens);

        return $this;
    }

    public function getOutputTokens(): int
    {
        return $this->outputTokens;
    }

    public function setOutputTokens(int $outputTokens): self
    {
        $this->outputTokens = max(0, $outputTokens);

        return $this;
    }

    public function getTotalCredits(): int
    {
        return $this->totalCredits;
    }

    public function setTotalCredits(int $totalCredits): self
    {
        $this->totalCredits = max(0, $totalCredits);

        return $this;
    }

    public function getDurationMs(): int
    {
        return $this->durationMs;
    }

    public function setDurationMs(int $durationMs): self
    {
        $this->durationMs = max(0, $durationMs);

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = mb_substr(trim($status), 0, 20);

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): self
    {
        if (null !== $errorMessage) {
            $errorMessage = mb_substr(trim($errorMessage), 0, 5000);
        }
        $this->errorMessage = '' === $errorMessage ? null : $errorMessage;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
