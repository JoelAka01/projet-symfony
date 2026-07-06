<?php

declare(strict_types=1);

namespace App\MessageHandler\Pipeline;

use App\Entity\TopicResearch;
use App\Enum\PipelineStatus;
use App\Message\Pipeline\AnalyzeIntelligenceMessage;
use App\Repository\TopicResearchRepository;
use App\Service\Pipeline\ArticleGenerationPipelineService;
use App\Service\Pipeline\IntelligenceAnalyzerService;
use App\Service\Pipeline\PipelineStepControlService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class AnalyzeIntelligenceHandler
{
    public function __construct(
        private readonly TopicResearchRepository $topicResearchRepository,
        private readonly IntelligenceAnalyzerService $intelligenceAnalyzer,
        private readonly ArticleGenerationPipelineService $pipelineService,
        private readonly PipelineStepControlService $stepControl,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(AnalyzeIntelligenceMessage $message): void
    {
        $topicResearch = $this->topicResearchRepository->find($message->getTopicResearchId());
        if (null === $topicResearch) {
            $this->logger->warning('Pipeline intelligence message ignored because the topic research no longer exists.', [
                'topic_research_id' => $message->getTopicResearchId(),
            ]);

            return;
        }

        try {
            $serpAnalysis = $topicResearch->getSerpAnalysis();
            if (null === $serpAnalysis) {
                throw new \RuntimeException('SERP analysis is required before the intelligence step.');
            }

            $disabledConfigs = $this->stepControl->disabledConfigsForPipelineStep(TopicResearch::STEP_INTELLIGENCE);
            if ([] !== $disabledConfigs) {
                $topicResearch->markRunning(TopicResearch::STEP_INTELLIGENCE, PipelineStatus::INTELLIGENCE_ANALYZING);
                $this->stepControl->logSkippedStep($topicResearch, TopicResearch::STEP_INTELLIGENCE, $disabledConfigs);
                $this->entityManager->persist($this->stepControl->fallbackIntelligenceAnalysis($topicResearch, 'step skipped by admin'));
                $this->stepControl->completeSkippedStep($topicResearch, TopicResearch::STEP_INTELLIGENCE);
                $this->entityManager->flush();
                $this->pipelineService->dispatchNext($topicResearch, TopicResearch::STEP_INTELLIGENCE);

                return;
            }

            $topicResearch->markRunning(TopicResearch::STEP_INTELLIGENCE, PipelineStatus::INTELLIGENCE_ANALYZING);
            $this->entityManager->flush();

            $intelligenceAnalysis = $this->intelligenceAnalyzer->analyze($topicResearch, $serpAnalysis);
            $topicResearch
                ->setIntelligenceAnalysis($intelligenceAnalysis)
                ->setCurrentStep(null)
                ->setStatus(PipelineStatus::INTELLIGENCE_ANALYZED);

            $this->entityManager->persist($intelligenceAnalysis);
            $this->entityManager->flush();
            $this->pipelineService->dispatchNext($topicResearch, TopicResearch::STEP_INTELLIGENCE);
        } catch (\Throwable $exception) {
            $topicResearch->markFailed(TopicResearch::STEP_INTELLIGENCE, $exception->getMessage());
            $this->entityManager->flush();
            $this->logger->error('Pipeline intelligence step failed.', [
                'topic_research_id' => $topicResearch->getId(),
                'exception' => $exception,
            ]);

            throw $exception;
        }
    }
}
