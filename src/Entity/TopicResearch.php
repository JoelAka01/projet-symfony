<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TimestampableTrait;
use App\Entity\Traits\UuidPrimaryKeyTrait;
use App\Enum\PipelineQualityMode;
use App\Enum\PipelineStatus;
use App\Repository\TopicResearchRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TopicResearchRepository::class)]
#[ORM\Table(name: 'topic_researches')]
#[ORM\Index(name: 'idx_topic_researches_status', columns: ['status'])]
#[ORM\Index(name: 'idx_topic_researches_project_created', columns: ['project_id', 'created_at'])]
class TopicResearch
{
    use TimestampableTrait;
    use UuidPrimaryKeyTrait;

    public const STEP_SERP_ANALYSIS = 'serp_analysis';
    public const STEP_INTELLIGENCE = 'intelligence';
    public const STEP_BRIEF_OUTLINE = 'brief_outline';
    public const STEP_ARTICLE = 'article';
    public const STEP_INTERNAL_LINKING = 'internal_linking';
    public const STEP_SEO_SCORE = 'seo_score';

    public const STEPS = [
        self::STEP_SERP_ANALYSIS,
        self::STEP_INTELLIGENCE,
        self::STEP_BRIEF_OUTLINE,
        self::STEP_ARTICLE,
        self::STEP_INTERNAL_LINKING,
        self::STEP_SEO_SCORE,
    ];

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'requested_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $requestedBy = null;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 200)]
    private string $primaryKeyword = '';

    #[ORM\Column(enumType: PipelineStatus::class)]
    private PipelineStatus $status = PipelineStatus::NEW;

    #[ORM\Column(enumType: PipelineQualityMode::class)]
    private PipelineQualityMode $qualityMode = PipelineQualityMode::BALANCED;

    #[ORM\Column(length: 10, nullable: true)]
    #[Assert\Length(max: 10)]
    private ?string $country = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[Assert\Length(max: 10)]
    private ?string $language = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $sector = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $audience = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $businessObjective = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $currentStep = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $failedStep = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\OneToOne(mappedBy: 'topicResearch', targetEntity: SerpAnalysis::class, cascade: ['persist', 'remove'])]
    private ?SerpAnalysis $serpAnalysis = null;

    #[ORM\OneToOne(mappedBy: 'topicResearch', targetEntity: IntelligenceAnalysis::class, cascade: ['persist', 'remove'])]
    private ?IntelligenceAnalysis $intelligenceAnalysis = null;

    #[ORM\OneToOne(mappedBy: 'topicResearch', targetEntity: ContentBrief::class, cascade: ['persist', 'remove'])]
    private ?ContentBrief $contentBrief = null;

    /** @var Collection<int, Article> */
    #[ORM\OneToMany(mappedBy: 'topicResearch', targetEntity: Article::class)]
    private Collection $articles;

    /** @var Collection<int, PipelineRunLog> */
    #[ORM\OneToMany(mappedBy: 'topicResearch', targetEntity: PipelineRunLog::class, orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $runLogs;

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->articles = new ArrayCollection();
        $this->runLogs = new ArrayCollection();
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

    public function getRequestedBy(): ?User
    {
        return $this->requestedBy;
    }

    public function setRequestedBy(?User $requestedBy): self
    {
        $this->requestedBy = $requestedBy;

        return $this;
    }

    public function getPrimaryKeyword(): string
    {
        return $this->primaryKeyword;
    }

    public function setPrimaryKeyword(string $primaryKeyword): self
    {
        $this->primaryKeyword = trim($primaryKeyword);

        return $this;
    }

    public function getStatus(): PipelineStatus
    {
        return $this->status;
    }

    public function setStatus(PipelineStatus $status): self
    {
        $this->status = $status;
        $this->touch();

        return $this;
    }

    public function getQualityMode(): PipelineQualityMode
    {
        return $this->qualityMode;
    }

    public function setQualityMode(PipelineQualityMode $qualityMode): self
    {
        $this->qualityMode = $qualityMode;
        $this->touch();

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): self
    {
        $this->country = $this->optionalString($country, 10);

        return $this;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(?string $language): self
    {
        $this->language = $this->optionalString($language, 10);

        return $this;
    }

    public function getSector(): ?string
    {
        return $this->sector;
    }

    public function setSector(?string $sector): self
    {
        $this->sector = $this->optionalString($sector, 180);

        return $this;
    }

    public function getAudience(): ?string
    {
        return $this->audience;
    }

    public function setAudience(?string $audience): self
    {
        $this->audience = $this->optionalString($audience);

        return $this;
    }

    public function getBusinessObjective(): ?string
    {
        return $this->businessObjective;
    }

    public function setBusinessObjective(?string $businessObjective): self
    {
        $this->businessObjective = $this->optionalString($businessObjective);

        return $this;
    }

    public function getCurrentStep(): ?string
    {
        return $this->currentStep;
    }

    public function setCurrentStep(?string $currentStep): self
    {
        $this->currentStep = $this->optionalString($currentStep, 40);
        $this->touch();

        return $this;
    }

    public function getFailedStep(): ?string
    {
        return $this->failedStep;
    }

    public function setFailedStep(?string $failedStep): self
    {
        $this->failedStep = $this->optionalString($failedStep, 40);
        $this->touch();

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): self
    {
        $this->errorMessage = $this->optionalString($errorMessage);
        $this->touch();

        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): self
    {
        $this->completedAt = $completedAt;
        $this->touch();

        return $this;
    }

    public function getSerpAnalysis(): ?SerpAnalysis
    {
        return $this->serpAnalysis;
    }

    public function setSerpAnalysis(?SerpAnalysis $serpAnalysis): self
    {
        $this->serpAnalysis = $serpAnalysis;
        if (null !== $serpAnalysis && $serpAnalysis->getTopicResearch() !== $this) {
            $serpAnalysis->setTopicResearch($this);
        }

        return $this;
    }

    public function getIntelligenceAnalysis(): ?IntelligenceAnalysis
    {
        return $this->intelligenceAnalysis;
    }

    public function setIntelligenceAnalysis(?IntelligenceAnalysis $intelligenceAnalysis): self
    {
        $this->intelligenceAnalysis = $intelligenceAnalysis;
        if (null !== $intelligenceAnalysis && $intelligenceAnalysis->getTopicResearch() !== $this) {
            $intelligenceAnalysis->setTopicResearch($this);
        }

        return $this;
    }

    public function getContentBrief(): ?ContentBrief
    {
        return $this->contentBrief;
    }

    public function setContentBrief(?ContentBrief $contentBrief): self
    {
        $this->contentBrief = $contentBrief;
        if (null !== $contentBrief && $contentBrief->getTopicResearch() !== $this) {
            $contentBrief->setTopicResearch($this);
        }

        return $this;
    }

    /** @return Collection<int, Article> */
    public function getArticles(): Collection
    {
        return $this->articles;
    }

    public function getArticle(): ?Article
    {
        $article = $this->articles->first();

        return false === $article ? null : $article;
    }

    public function addArticle(Article $article): self
    {
        if (!$this->articles->contains($article)) {
            $this->articles->add($article);
            $article->setTopicResearch($this);
        }

        return $this;
    }

    public function removeArticle(Article $article): self
    {
        if ($this->articles->removeElement($article) && $article->getTopicResearch() === $this) {
            $article->setTopicResearch(null);
        }

        return $this;
    }

    /** @return Collection<int, PipelineRunLog> */
    public function getRunLogs(): Collection
    {
        return $this->runLogs;
    }

    public function addRunLog(PipelineRunLog $runLog): self
    {
        if (!$this->runLogs->contains($runLog)) {
            $this->runLogs->add($runLog);
            $runLog->setTopicResearch($this);
        }

        return $this;
    }

    public function markRunning(string $step, PipelineStatus $status): self
    {
        $this->status = $status;
        $this->currentStep = $step;
        $this->failedStep = null;
        $this->errorMessage = null;
        $this->touch();

        return $this;
    }

    public function markFailed(string $step, string $message): self
    {
        $this->status = PipelineStatus::FAILED;
        $this->currentStep = null;
        $this->failedStep = $step;
        $this->errorMessage = mb_substr($message, 0, 5000);
        $this->touch();

        return $this;
    }

    public function clearFailure(): self
    {
        $this->failedStep = null;
        $this->errorMessage = null;
        $this->touch();

        return $this;
    }

    public function canRetryStep(string $step): bool
    {
        if (!in_array($step, self::STEPS, true)) {
            return false;
        }

        if (PipelineStatus::FAILED === $this->status) {
            return $this->failedStep === $step;
        }

        if (self::STEP_SERP_ANALYSIS === $step) {
            return null !== $this->serpAnalysis;
        }
        if (self::STEP_INTELLIGENCE === $step) {
            return null !== $this->intelligenceAnalysis;
        }
        if (self::STEP_BRIEF_OUTLINE === $step) {
            return null !== $this->contentBrief;
        }
        if (self::STEP_ARTICLE === $step) {
            return null !== $this->getArticle()?->getContentHtml();
        }
        if (self::STEP_INTERNAL_LINKING === $step) {
            return null !== $this->getArticle()?->getContentHtml();
        }

        return null !== $this->getArticle()?->getSeoScore();
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
