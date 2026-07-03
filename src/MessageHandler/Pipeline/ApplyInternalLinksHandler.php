<?php

declare(strict_types=1);

namespace App\MessageHandler\Pipeline;

use App\Entity\PipelineRunLog;
use App\Entity\TopicResearch;
use App\Enum\PipelineStatus;
use App\Message\Pipeline\ApplyInternalLinksMessage;
use App\Repository\TopicResearchRepository;
use App\Service\InternalLinking\InternalLinkingService;
use App\Service\Pipeline\ArticleGenerationPipelineService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ApplyInternalLinksHandler
{
    public function __construct(
        private readonly TopicResearchRepository $topicResearchRepository,
        private readonly InternalLinkingService $internalLinkingService,
        private readonly ArticleGenerationPipelineService $pipelineService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(ApplyInternalLinksMessage $message): void
    {
        $topicResearch = $this->topicResearchRepository->find($message->getTopicResearchId());
        if (null === $topicResearch) {
            $this->logger->warning('Pipeline internal linking message ignored because the topic research no longer exists.', [
                'topic_research_id' => $message->getTopicResearchId(),
            ]);

            return;
        }

        $startedAt = microtime(true);
        $runLog = $this->createRunLog($topicResearch);
        $this->entityManager->persist($runLog);

        try {
            $article = $topicResearch->getArticle();
            $project = $topicResearch->getProject();
            if (null === $article || null === $project || null === $article->getContentHtml()) {
                throw new \RuntimeException('Project and generated article content are required before internal linking.');
            }

            $topicResearch->markRunning(TopicResearch::STEP_INTERNAL_LINKING, PipelineStatus::INTERNAL_LINKING);
            $this->entityManager->flush();

            $summary = $this->internalLinkingService->apply($article, $project, $article->getContentHtml());

            $runLog
                ->setParsedResponse($summary)
                ->setRawResponse(json_encode($summary, JSON_THROW_ON_ERROR))
                ->setDurationMs($this->durationMs($startedAt))
                ->setStatus(PipelineRunLog::STATUS_SUCCESS);

            $topicResearch
                ->setCurrentStep(null)
                ->setStatus(PipelineStatus::INTERNAL_LINKED);

            $this->entityManager->flush();
            $this->pipelineService->dispatchNext($topicResearch, TopicResearch::STEP_INTERNAL_LINKING);
        } catch (\Throwable $exception) {
            $runLog
                ->setDurationMs($this->durationMs($startedAt))
                ->setStatus(PipelineRunLog::STATUS_FAILED)
                ->setErrorMessage($exception->getMessage());

            $topicResearch->markFailed(TopicResearch::STEP_INTERNAL_LINKING, $exception->getMessage());
            $this->entityManager->flush();
            $this->logger->error('Pipeline internal linking step failed.', [
                'topic_research_id' => $topicResearch->getId(),
                'exception' => $exception,
            ]);

            throw $exception;
        }
    }

    private function createRunLog(TopicResearch $topicResearch): PipelineRunLog
    {
        return (new PipelineRunLog())
            ->setTopicResearch($topicResearch)
            ->setStep(TopicResearch::STEP_INTERNAL_LINKING)
            ->setAttempt($this->nextAttempt($topicResearch))
            ->setPromptSent('Apply internal links from active SitePage records.')
            ->setProvider('local')
            ->setModel('internal-linking-mvp')
            ->setStatus(PipelineRunLog::STATUS_SUCCESS);
    }

    private function nextAttempt(TopicResearch $topicResearch): int
    {
        $attempt = 1;
        foreach ($topicResearch->getRunLogs() as $runLog) {
            if (TopicResearch::STEP_INTERNAL_LINKING === $runLog->getStep()) {
                $attempt = max($attempt, $runLog->getAttempt() + 1);
            }
        }

        return $attempt;
    }

    private function durationMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
