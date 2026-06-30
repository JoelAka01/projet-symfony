<?php

declare(strict_types=1);

namespace App\Service\Pipeline;

use App\Entity\Project;
use App\Entity\TopicResearch;
use App\Entity\User;
use App\Enum\PipelineStatus;
use App\Message\Pipeline\AnalyzeIntelligenceMessage;
use App\Message\Pipeline\AnalyzeSerpMessage;
use App\Message\Pipeline\GenerateArticleMessage;
use App\Message\Pipeline\GenerateBriefMessage;
use App\Message\Pipeline\OptimizeSeoMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class ArticleGenerationPipelineService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
    ) {}

    /**
     * @param array{
     *     country?: string|null,
     *     language?: string|null,
     *     sector?: string|null,
     *     audience?: string|null,
     *     businessObjective?: string|null
     * } $options
     */
    public function start(Project $project, User $user, string $keyword, array $options = []): TopicResearch
    {
        $topicResearch = new TopicResearch();
        $topicResearch
            ->setProject($project)
            ->setRequestedBy($user)
            ->setPrimaryKeyword($keyword)
            ->setCountry((string) ($options['country'] ?? $project->getTargetCountry() ?? 'FR'))
            ->setLanguage((string) ($options['language'] ?? $project->getDefaultLanguage() ?? 'fr'))
            ->setSector($this->nullableString($options['sector'] ?? null))
            ->setAudience($this->nullableString($options['audience'] ?? null))
            ->setBusinessObjective($this->nullableString($options['businessObjective'] ?? null))
            ->setStatus(PipelineStatus::NEW);

        $this->entityManager->persist($topicResearch);
        $this->entityManager->flush();

        $this->dispatchStep($topicResearch, TopicResearch::STEP_SERP_ANALYSIS);

        return $topicResearch;
    }

    public function retryStep(TopicResearch $topicResearch, ?string $step = null): void
    {
        $step ??= $topicResearch->getFailedStep();
        if (null === $step || !$topicResearch->canRetryStep($step)) {
            throw new \InvalidArgumentException('This pipeline step cannot be retried.');
        }

        $topicResearch
            ->clearFailure()
            ->setStatus($this->resetStatusForStep($step))
            ->setCurrentStep(null);

        $this->entityManager->flush();
        $this->dispatchStep($topicResearch, $step);
    }

    public function dispatchNext(TopicResearch $topicResearch, string $completedStep): void
    {
        $nextStep = match ($completedStep) {
            TopicResearch::STEP_SERP_ANALYSIS => TopicResearch::STEP_INTELLIGENCE,
            TopicResearch::STEP_INTELLIGENCE => TopicResearch::STEP_BRIEF_OUTLINE,
            TopicResearch::STEP_BRIEF_OUTLINE => TopicResearch::STEP_ARTICLE,
            TopicResearch::STEP_ARTICLE => TopicResearch::STEP_SEO_SCORE,
            default => null,
        };

        if (null !== $nextStep) {
            $this->dispatchStep($topicResearch, $nextStep);
        }
    }

    public function dispatchStep(TopicResearch $topicResearch, string $step): void
    {
        $message = match ($step) {
            TopicResearch::STEP_SERP_ANALYSIS => new AnalyzeSerpMessage($topicResearch->getId()),
            TopicResearch::STEP_INTELLIGENCE => new AnalyzeIntelligenceMessage($topicResearch->getId()),
            TopicResearch::STEP_BRIEF_OUTLINE => new GenerateBriefMessage($topicResearch->getId()),
            TopicResearch::STEP_ARTICLE => new GenerateArticleMessage($topicResearch->getId()),
            TopicResearch::STEP_SEO_SCORE => new OptimizeSeoMessage($topicResearch->getId()),
            default => throw new \InvalidArgumentException(sprintf('Unknown pipeline step "%s".', $step)),
        };

        $this->messageBus->dispatch($message);
    }

    private function resetStatusForStep(string $step): PipelineStatus
    {
        return match ($step) {
            TopicResearch::STEP_SERP_ANALYSIS => PipelineStatus::NEW,
            TopicResearch::STEP_INTELLIGENCE => PipelineStatus::SERP_ANALYZED,
            TopicResearch::STEP_BRIEF_OUTLINE => PipelineStatus::INTELLIGENCE_ANALYZED,
            TopicResearch::STEP_ARTICLE => PipelineStatus::BRIEF_READY,
            TopicResearch::STEP_SEO_SCORE => PipelineStatus::CONTENT_GENERATED,
            default => throw new \InvalidArgumentException(sprintf('Unknown pipeline step "%s".', $step)),
        };
    }

    private function nullableString(mixed $value): ?string
    {
        if (!is_scalar($value) || '' === trim((string) $value)) {
            return null;
        }

        return trim((string) $value);
    }
}
