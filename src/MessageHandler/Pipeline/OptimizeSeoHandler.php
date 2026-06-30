<?php

declare(strict_types=1);

namespace App\MessageHandler\Pipeline;

use App\Entity\TopicResearch;
use App\Enum\PipelineStatus;
use App\Message\Pipeline\OptimizeSeoMessage;
use App\Repository\TopicResearchRepository;
use App\Service\Pipeline\SeoScorerService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class OptimizeSeoHandler
{
    public function __construct(
        private readonly TopicResearchRepository $topicResearchRepository,
        private readonly SeoScorerService $seoScorer,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(OptimizeSeoMessage $message): void
    {
        $topicResearch = $this->topicResearchRepository->find($message->getTopicResearchId());
        if (null === $topicResearch) {
            $this->logger->warning('Pipeline SEO message ignored because the topic research no longer exists.', [
                'topic_research_id' => $message->getTopicResearchId(),
            ]);

            return;
        }

        try {
            $contentBrief = $topicResearch->getContentBrief();
            $article = $topicResearch->getArticle();
            if (null === $contentBrief || null === $article) {
                throw new \RuntimeException('Content brief and article are required before the SEO score step.');
            }

            $topicResearch->markRunning(TopicResearch::STEP_SEO_SCORE, PipelineStatus::SEO_OPTIMIZING);
            $this->entityManager->flush();

            $this->seoScorer->score($topicResearch, $article, $contentBrief);
            $topicResearch
                ->setCurrentStep(null)
                ->setStatus(PipelineStatus::READY_TO_PUBLISH)
                ->setCompletedAt(new \DateTimeImmutable());

            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            $topicResearch->markFailed(TopicResearch::STEP_SEO_SCORE, $exception->getMessage());
            $this->entityManager->flush();
            $this->logger->error('Pipeline SEO score step failed.', [
                'topic_research_id' => $topicResearch->getId(),
                'exception' => $exception,
            ]);

            throw $exception;
        }
    }
}
