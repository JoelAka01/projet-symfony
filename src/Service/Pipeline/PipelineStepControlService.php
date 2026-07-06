<?php

declare(strict_types=1);

namespace App\Service\Pipeline;

use App\Entity\ContentBrief;
use App\Entity\IntelligenceAnalysis;
use App\Entity\PipelineRunLog;
use App\Entity\PipelineStepConfig;
use App\Entity\SerpAnalysis;
use App\Entity\TopicResearch;
use App\Enum\PipelineStatus;
use App\Repository\PipelineStepConfigRepository;
use Doctrine\ORM\EntityManagerInterface;

final class PipelineStepControlService
{
    /** @var list<array{key: string, label: string, required: bool, fallback: string, warning: ?string}> */
    private const DEFINITIONS = [
        ['key' => PipelineStepConfig::SERP_INTELLIGENCE, 'label' => 'SERP Intelligence', 'required' => false, 'fallback' => PipelineStepConfig::FALLBACK_REUSE_OR_EMPTY, 'warning' => 'Attention: disabling SERP Intelligence can reduce the SEO quality of generated articles.'],
        ['key' => PipelineStepConfig::QUESTION_INTELLIGENCE, 'label' => 'Question Intelligence', 'required' => false, 'fallback' => PipelineStepConfig::FALLBACK_REUSE_OR_EMPTY, 'warning' => 'Attention: disabling Question Intelligence can reduce FAQ and search-intent coverage.'],
        ['key' => PipelineStepConfig::INTENT_DETECTION, 'label' => 'Intent Detection', 'required' => false, 'fallback' => PipelineStepConfig::FALLBACK_REUSE_OR_EMPTY, 'warning' => 'Attention: disabling Intent Detection can make briefs less aligned with search intent.'],
        ['key' => PipelineStepConfig::ENTITY_EXTRACTION, 'label' => 'Entity Extraction', 'required' => false, 'fallback' => PipelineStepConfig::FALLBACK_REUSE_OR_EMPTY, 'warning' => 'Attention: disabling Entity Extraction can reduce topical depth and GEO readiness.'],
        ['key' => PipelineStepConfig::CONTENT_BRIEF, 'label' => 'Content Brief', 'required' => false, 'fallback' => PipelineStepConfig::FALLBACK_REUSE_OR_EMPTY, 'warning' => 'Attention: disabling Content Brief can make the article less structured.'],
        ['key' => PipelineStepConfig::OUTLINE_BUILDER, 'label' => 'Outline Builder', 'required' => false, 'fallback' => PipelineStepConfig::FALLBACK_REUSE_OR_EMPTY, 'warning' => 'Attention: disabling Outline Builder can reduce heading and section quality.'],
        ['key' => PipelineStepConfig::INTERNAL_LINKING, 'label' => 'Internal Linking', 'required' => false, 'fallback' => PipelineStepConfig::FALLBACK_CONTINUE, 'warning' => 'Attention: disabling Internal Linking can reduce SEO and conversion pathways.'],
        ['key' => PipelineStepConfig::SEO_SCORE, 'label' => 'SEO Score', 'required' => false, 'fallback' => PipelineStepConfig::FALLBACK_CONTINUE, 'warning' => 'Attention: disabling SEO Score removes the AI review score; Quality Gate still runs.'],
        ['key' => PipelineStepConfig::EEAT_OPTIMIZER, 'label' => 'EEAT Optimizer', 'required' => false, 'fallback' => PipelineStepConfig::FALLBACK_CONTINUE, 'warning' => 'Attention: disabling EEAT Optimizer can reduce trust and authoritativeness signals.'],
        ['key' => PipelineStepConfig::GEO_OPTIMIZER, 'label' => 'GEO Optimizer', 'required' => false, 'fallback' => PipelineStepConfig::FALLBACK_CONTINUE, 'warning' => 'Attention: disabling GEO Optimizer can reduce AI citation readiness.'],
        ['key' => PipelineStepConfig::AUTO_PUBLISH, 'label' => 'Auto Publish', 'required' => false, 'fallback' => PipelineStepConfig::FALLBACK_CONTINUE, 'warning' => 'Attention: disabling Auto Publish keeps articles in review/draft mode.'],
        ['key' => PipelineStepConfig::ARTICLE_GENERATION, 'label' => 'Article Generation', 'required' => true, 'fallback' => PipelineStepConfig::FALLBACK_REQUIRED, 'warning' => null],
        ['key' => PipelineStepConfig::HTML_SANITIZATION, 'label' => 'HTML Sanitization', 'required' => true, 'fallback' => PipelineStepConfig::FALLBACK_REQUIRED, 'warning' => null],
        ['key' => PipelineStepConfig::QUALITY_GATE, 'label' => 'Quality Gate', 'required' => true, 'fallback' => PipelineStepConfig::FALLBACK_REQUIRED, 'warning' => null],
        ['key' => PipelineStepConfig::ERROR_LOGGING, 'label' => 'Error Logging', 'required' => true, 'fallback' => PipelineStepConfig::FALLBACK_REQUIRED, 'warning' => null],
    ];

    public function __construct(
        private readonly PipelineStepConfigRepository $configRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /** @return list<array{config: PipelineStepConfig, warning: ?string}> */
    public function ensureDefaults(): array
    {
        $configs = $this->configRepository->findIndexedByStepKey();
        $rows = [];

        foreach (self::DEFINITIONS as $definition) {
            $config = $configs[$definition['key']] ?? null;
            if (!$config instanceof PipelineStepConfig) {
                $config = (new PipelineStepConfig())
                    ->setStepKey($definition['key'])
                    ->setLabel($definition['label'])
                    ->setIsRequired($definition['required'])
                    ->setFallbackMode($definition['fallback'])
                    ->setIsEnabled(true);
                $this->entityManager->persist($config);
            } else {
                $config
                    ->setLabel($definition['label'])
                    ->setIsRequired($definition['required'])
                    ->setFallbackMode($definition['fallback']);
            }

            $rows[] = [
                'config' => $config,
                'warning' => $definition['warning'],
            ];
        }

        $this->entityManager->flush();

        return $rows;
    }

    public function isEnabled(string $stepKey): bool
    {
        $definition = $this->definition($stepKey);
        if (true === ($definition['required'] ?? false)) {
            return true;
        }

        $config = $this->configRepository->findOneByStepKey($stepKey);

        return null === $config || $config->isEnabled();
    }

    /** @return list<PipelineStepConfig> */
    public function disabledConfigsForPipelineStep(string $pipelineStep): array
    {
        $stepKeys = match ($pipelineStep) {
            TopicResearch::STEP_SERP_ANALYSIS => [
                PipelineStepConfig::SERP_INTELLIGENCE,
                PipelineStepConfig::QUESTION_INTELLIGENCE,
            ],
            TopicResearch::STEP_INTELLIGENCE => [
                PipelineStepConfig::INTENT_DETECTION,
                PipelineStepConfig::ENTITY_EXTRACTION,
            ],
            TopicResearch::STEP_BRIEF_OUTLINE => [
                PipelineStepConfig::CONTENT_BRIEF,
                PipelineStepConfig::OUTLINE_BUILDER,
            ],
            TopicResearch::STEP_INTERNAL_LINKING => [PipelineStepConfig::INTERNAL_LINKING],
            TopicResearch::STEP_SEO_SCORE => [PipelineStepConfig::SEO_SCORE],
            default => [],
        };

        $disabled = [];
        foreach ($stepKeys as $stepKey) {
            $config = $this->configRepository->findOneByStepKey($stepKey);
            if ($config instanceof PipelineStepConfig && !$config->isEnabled() && !$config->isRequired()) {
                $disabled[] = $config;
            }
        }

        return $disabled;
    }

    /** @param list<PipelineStepConfig> $disabledConfigs */
    public function logSkippedStep(TopicResearch $topicResearch, string $pipelineStep, array $disabledConfigs): void
    {
        foreach ($disabledConfigs as $config) {
            $runLog = (new PipelineRunLog())
                ->setTopicResearch($topicResearch)
                ->setStep($pipelineStep)
                ->setAttempt($this->nextAttempt($topicResearch, $pipelineStep))
                ->setPromptSent('step skipped by admin')
                ->setProvider('admin')
                ->setModel($config->getStepKey())
                ->setStatus(PipelineRunLog::STATUS_SKIPPED)
                ->setParsedResponse([
                    'message' => 'step skipped by admin',
                    'step_key' => $config->getStepKey(),
                    'fallback_mode' => $config->getFallbackMode(),
                ])
                ->setRawResponse(json_encode([
                    'message' => 'step skipped by admin',
                    'step_key' => $config->getStepKey(),
                    'fallback_mode' => $config->getFallbackMode(),
                ], JSON_THROW_ON_ERROR));
            $this->entityManager->persist($runLog);
        }
    }

    public function fallbackSerpAnalysis(TopicResearch $topicResearch, string $reason): SerpAnalysis
    {
        $analysis = $topicResearch->getSerpAnalysis() ?? new SerpAnalysis();
        $analysis
            ->setTopicResearch($topicResearch)
            ->setCompetitors([])
            ->setSerpFeatures([])
            ->setContentGaps([])
            ->setQuestions([])
            ->setAverageWordCount(0)
            ->setTotalQuestions(0)
            ->setRawSerpResponse(['fallback' => $reason])
            ->setAnalyzedAt(new \DateTimeImmutable());

        return $analysis;
    }

    public function fallbackIntelligenceAnalysis(TopicResearch $topicResearch, string $reason): IntelligenceAnalysis
    {
        $analysis = $topicResearch->getIntelligenceAnalysis() ?? new IntelligenceAnalysis();
        $analysis
            ->setTopicResearch($topicResearch)
            ->setPrimaryIntent('')
            ->setIntentBreakdown([])
            ->setEntities([])
            ->setSemanticConcepts([[
                'concept' => $topicResearch->getPrimaryKeyword(),
                'source' => $reason,
            ]])
            ->setAnalyzedAt(new \DateTimeImmutable());

        return $analysis;
    }

    public function fallbackContentBrief(TopicResearch $topicResearch, ?IntelligenceAnalysis $intelligenceAnalysis, string $reason): ContentBrief
    {
        $keyword = $topicResearch->getPrimaryKeyword();
        $brief = $topicResearch->getContentBrief() ?? new ContentBrief();
        $brief
            ->setTopicResearch($topicResearch)
            ->setTargetAudience($topicResearch->getAudience())
            ->setObjective($topicResearch->getBusinessObjective())
            ->setIntent($intelligenceAnalysis?->getPrimaryIntent())
            ->setToneRecommendation('Expert, clear, useful')
            ->setTargetWordCount($topicResearch->getTargetWordCount())
            ->setKeyEntities($intelligenceAnalysis?->getEntities() ?? [])
            ->setKeyQuestions([])
            ->setCompetitorInsights([])
            ->setCta($topicResearch->getBusinessObjective())
            ->setSources([])
            ->setSeoTargets([
                'primary_keyword' => $keyword,
                'secondary_keywords' => [],
                'lsi_terms' => [],
                'fallback' => $reason,
            ])
            ->setOutline([
                [
                    'level' => 'h2',
                    'title' => $keyword,
                    'key_points' => ['Cover the topic clearly and answer the search need.'],
                    'questions_answered' => [],
                    'entities_covered' => [$keyword],
                ],
            ])
            ->setFaqSuggestions([])
            ->setTableSuggestions([])
            ->setEstimatedWordCount($topicResearch->getTargetWordCount())
            ->setGeneratedAt(new \DateTimeImmutable());

        return $brief;
    }

    public function completeSkippedStep(TopicResearch $topicResearch, string $pipelineStep): void
    {
        $topicResearch
            ->setCurrentStep(null)
            ->setStatus(match ($pipelineStep) {
                TopicResearch::STEP_SERP_ANALYSIS => PipelineStatus::SERP_ANALYZED,
                TopicResearch::STEP_INTELLIGENCE => PipelineStatus::INTELLIGENCE_ANALYZED,
                TopicResearch::STEP_BRIEF_OUTLINE => PipelineStatus::BRIEF_READY,
                TopicResearch::STEP_INTERNAL_LINKING => PipelineStatus::INTERNAL_LINKED,
                default => $topicResearch->getStatus(),
            });
    }

    /** @return array{key: string, label: string, required: bool, fallback: string, warning: ?string}|null */
    private function definition(string $stepKey): ?array
    {
        $stepKey = strtoupper(trim($stepKey));
        foreach (self::DEFINITIONS as $definition) {
            if ($definition['key'] === $stepKey) {
                return $definition;
            }
        }

        return null;
    }

    private function nextAttempt(TopicResearch $topicResearch, string $pipelineStep): int
    {
        $attempt = 1;
        foreach ($topicResearch->getRunLogs() as $runLog) {
            if ($runLog->getStep() === $pipelineStep) {
                $attempt = max($attempt, $runLog->getAttempt() + 1);
            }
        }

        return $attempt;
    }
}
