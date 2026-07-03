<?php

declare(strict_types=1);

namespace App\MessageHandler\Pipeline;

use App\Entity\TopicResearch;
use App\Enum\PipelineStatus;
use App\Message\Pipeline\GenerateBriefMessage;
use App\Repository\TopicResearchRepository;
use App\Service\Pipeline\ArticleGenerationPipelineService;
use App\Service\Pipeline\BriefOutlineGeneratorService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GenerateBriefHandler
{
    public function __construct(
        private readonly TopicResearchRepository $topicResearchRepository,
        private readonly BriefOutlineGeneratorService $briefOutlineGenerator,
        private readonly ArticleGenerationPipelineService $pipelineService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(GenerateBriefMessage $message): void
    {
        $topicResearch = $this->topicResearchRepository->find($message->getTopicResearchId());
        if (null === $topicResearch) {
            $this->logger->warning('Pipeline brief message ignored because the topic research no longer exists.', [
                'topic_research_id' => $message->getTopicResearchId(),
            ]);

            return;
        }

        try {
            $serpAnalysis = $topicResearch->getSerpAnalysis();
            $intelligenceAnalysis = $topicResearch->getIntelligenceAnalysis();
            if (null === $serpAnalysis || null === $intelligenceAnalysis) {
                throw new \RuntimeException('SERP and intelligence analyses are required before the brief step.');
            }

            $topicResearch->markRunning(TopicResearch::STEP_BRIEF_OUTLINE, PipelineStatus::BRIEF_GENERATING);
            $this->entityManager->flush();

            $brief = $this->briefOutlineGenerator->generate($topicResearch, $serpAnalysis, $intelligenceAnalysis);
            $topicResearch
                ->setContentBrief($brief)
                ->setCurrentStep(null)
                ->setStatus(PipelineStatus::BRIEF_READY);

            $this->entityManager->persist($brief);
            $this->entityManager->flush();
            $this->pipelineService->dispatchNext($topicResearch, TopicResearch::STEP_BRIEF_OUTLINE);
        } catch (\Throwable $exception) {
            $topicResearch->markFailed(TopicResearch::STEP_BRIEF_OUTLINE, $exception->getMessage());
            $this->entityManager->flush();
            $this->logger->error('Pipeline brief step failed.', [
                'topic_research_id' => $topicResearch->getId(),
                'exception' => $exception,
            ]);

            throw $exception;
        }
    }
}
