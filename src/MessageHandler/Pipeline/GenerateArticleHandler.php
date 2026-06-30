<?php

declare(strict_types=1);

namespace App\MessageHandler\Pipeline;

use App\Entity\TopicResearch;
use App\Enum\PipelineStatus;
use App\Message\Pipeline\GenerateArticleMessage;
use App\Repository\TopicResearchRepository;
use App\Service\Pipeline\ArticleGenerationPipelineService;
use App\Service\Pipeline\PipelineArticleWriterService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GenerateArticleHandler
{
    public function __construct(
        private readonly TopicResearchRepository $topicResearchRepository,
        private readonly PipelineArticleWriterService $articleWriter,
        private readonly ArticleGenerationPipelineService $pipelineService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(GenerateArticleMessage $message): void
    {
        $topicResearch = $this->topicResearchRepository->find($message->getTopicResearchId());
        if (null === $topicResearch) {
            $this->logger->warning('Pipeline article message ignored because the topic research no longer exists.', [
                'topic_research_id' => $message->getTopicResearchId(),
            ]);

            return;
        }

        try {
            $serpAnalysis = $topicResearch->getSerpAnalysis();
            $intelligenceAnalysis = $topicResearch->getIntelligenceAnalysis();
            $contentBrief = $topicResearch->getContentBrief();
            if (null === $serpAnalysis || null === $intelligenceAnalysis || null === $contentBrief) {
                throw new \RuntimeException('SERP, intelligence, and brief data are required before the article step.');
            }

            $topicResearch->markRunning(TopicResearch::STEP_ARTICLE, PipelineStatus::CONTENT_GENERATING);
            $this->entityManager->flush();

            $article = $this->articleWriter->write($topicResearch, $contentBrief, $intelligenceAnalysis, $serpAnalysis);
            $topicResearch
                ->addArticle($article)
                ->setCurrentStep(null)
                ->setStatus(PipelineStatus::CONTENT_GENERATED);

            $this->entityManager->flush();
            $this->pipelineService->dispatchNext($topicResearch, TopicResearch::STEP_ARTICLE);
        } catch (\Throwable $exception) {
            $topicResearch->markFailed(TopicResearch::STEP_ARTICLE, $exception->getMessage());
            $this->entityManager->flush();
            $this->logger->error('Pipeline article step failed.', [
                'topic_research_id' => $topicResearch->getId(),
                'exception' => $exception,
            ]);

            throw $exception;
        }
    }
}
