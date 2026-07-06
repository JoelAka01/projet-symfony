<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\UuidPrimaryKeyTrait;
use App\Repository\PipelineStepConfigRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PipelineStepConfigRepository::class)]
#[ORM\Table(name: 'pipeline_step_configs')]
#[ORM\UniqueConstraint(name: 'uniq_pipeline_step_configs_step_key', columns: ['step_key'])]
class PipelineStepConfig
{
    use UuidPrimaryKeyTrait;

    public const FALLBACK_CONTINUE = 'continue';
    public const FALLBACK_REUSE_OR_EMPTY = 'reuse_or_empty';
    public const FALLBACK_REQUIRED = 'required';

    public const SERP_INTELLIGENCE = 'SERP_INTELLIGENCE';
    public const QUESTION_INTELLIGENCE = 'QUESTION_INTELLIGENCE';
    public const INTENT_DETECTION = 'INTENT_DETECTION';
    public const ENTITY_EXTRACTION = 'ENTITY_EXTRACTION';
    public const CONTENT_BRIEF = 'CONTENT_BRIEF';
    public const OUTLINE_BUILDER = 'OUTLINE_BUILDER';
    public const INTERNAL_LINKING = 'INTERNAL_LINKING';
    public const SEO_SCORE = 'SEO_SCORE';
    public const EEAT_OPTIMIZER = 'EEAT_OPTIMIZER';
    public const GEO_OPTIMIZER = 'GEO_OPTIMIZER';
    public const AUTO_PUBLISH = 'AUTO_PUBLISH';
    public const ARTICLE_GENERATION = 'ARTICLE_GENERATION';
    public const HTML_SANITIZATION = 'HTML_SANITIZATION';
    public const QUALITY_GATE = 'QUALITY_GATE';
    public const ERROR_LOGGING = 'ERROR_LOGGING';

    #[ORM\Column(length: 80)]
    private string $stepKey = '';

    #[ORM\Column(length: 120)]
    private string $label = '';

    #[ORM\Column]
    private bool $isEnabled = true;

    #[ORM\Column]
    private bool $isRequired = false;

    #[ORM\Column(length: 40)]
    private string $fallbackMode = self::FALLBACK_CONTINUE;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->initializeUuid();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getStepKey(): string
    {
        return $this->stepKey;
    }

    public function setStepKey(string $stepKey): self
    {
        $stepKey = mb_substr(strtoupper(trim($stepKey)), 0, 80);
        if ($this->stepKey !== $stepKey) {
            $this->stepKey = $stepKey;
            $this->touch();
        }

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $label = mb_substr(trim($label), 0, 120);
        if ($this->label !== $label) {
            $this->label = $label;
            $this->touch();
        }

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function setIsEnabled(bool $isEnabled): self
    {
        $isEnabled = $this->isRequired ? true : $isEnabled;
        if ($this->isEnabled !== $isEnabled) {
            $this->isEnabled = $isEnabled;
            $this->touch();
        }

        return $this;
    }

    public function isRequired(): bool
    {
        return $this->isRequired;
    }

    public function setIsRequired(bool $isRequired): self
    {
        if ($this->isRequired !== $isRequired) {
            $this->isRequired = $isRequired;
            $this->touch();
        }
        if ($isRequired && (!$this->isEnabled || self::FALLBACK_REQUIRED !== $this->fallbackMode)) {
            $this->isEnabled = true;
            $this->fallbackMode = self::FALLBACK_REQUIRED;
            $this->touch();
        }

        return $this;
    }

    public function getFallbackMode(): string
    {
        return $this->fallbackMode;
    }

    public function setFallbackMode(string $fallbackMode): self
    {
        $fallbackMode = $this->isRequired ? self::FALLBACK_REQUIRED : mb_substr(trim($fallbackMode), 0, 40);
        if ($this->fallbackMode !== $fallbackMode) {
            $this->fallbackMode = $fallbackMode;
            $this->touch();
        }

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
