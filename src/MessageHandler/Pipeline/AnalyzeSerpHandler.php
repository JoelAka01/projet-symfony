<?php

declare(strict_types=1);

namespace App\MessageHandler\Pipeline;

use App\Dto\SerpResultDto;
use App\Entity\TopicResearch;
use App\Enum\PipelineStatus;
use App\Message\Pipeline\AnalyzeSerpMessage;
use App\Repository\TopicResearchRepository;
use App\Service\Cost\AuditDataReuseService;
use App\Service\Cost\CachedSerpService;
use App\Service\Pipeline\ArticleGenerationPipelineService;
use App\Service\Pipeline\SerpQuestionAnalyzerService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class AnalyzeSerpHandler
{
    public function __construct(
        private readonly TopicResearchRepository $topicResearchRepository,
        private readonly CachedSerpService $cachedSerpService,
        private readonly AuditDataReuseService $auditDataReuseService,
        private readonly SerpQuestionAnalyzerService $serpQuestionAnalyzer,
        private readonly ArticleGenerationPipelineService $pipelineService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(AnalyzeSerpMessage $message): void
    {
        $topicResearch = $this->topicResearchRepository->find($message->getTopicResearchId());
        if (null === $topicResearch) {
            $this->logger->warning('Pipeline SERP message ignored because the topic research no longer exists.', [
                'topic_research_id' => $message->getTopicResearchId(),
            ]);

            return;
        }

        try {
            $topicResearch->markRunning(TopicResearch::STEP_SERP_ANALYSIS, PipelineStatus::SERP_ANALYZING);
            $this->entityManager->flush();

            $auditSerpAnalysis = $this->auditDataReuseService->buildSerpAnalysis($topicResearch);
            if (null !== $auditSerpAnalysis && !$topicResearch->getQualityMode()->allowsRefresh()) {
                $topicResearch
                    ->setSerpAnalysis($auditSerpAnalysis)
                    ->setCurrentStep(null)
                    ->setStatus(PipelineStatus::SERP_ANALYZED);

                $this->entityManager->persist($auditSerpAnalysis);
                $this->entityManager->flush();
                $this->pipelineService->dispatchNext($topicResearch, TopicResearch::STEP_SERP_ANALYSIS);

                return;
            }

            $project = $topicResearch->getProject();
            if (null === $project) {
                throw new \RuntimeException('Project is required before the SERP step.');
            }

            $country = $topicResearch->getCountry() ?? 'FR';
            $language = $topicResearch->getLanguage() ?? 'fr';
            $serpResult = new SerpResultDto([], [], [], [], []);
            try {
                $serpResult = $this->cachedSerpService->search($project, $topicResearch->getPrimaryKeyword(), $country, $language, $topicResearch->getQualityMode());
            } catch (\Throwable $exception) {
                $this->logger->warning('Pipeline SERP search unavailable; continuing with empty SERP data.', [
                    'topic_research_id' => $topicResearch->getId(),
                    'error' => $exception->getMessage(),
                ]);
            }

            $suggestions = [];
            try {
                $suggestions = $this->cachedSerpService->suggest($project, $topicResearch->getPrimaryKeyword(), $country, $language, $topicResearch->getQualityMode());
            } catch (\Throwable $exception) {
                $this->logger->warning('Pipeline SERP suggestions unavailable; continuing with search results only.', [
                    'topic_research_id' => $topicResearch->getId(),
                    'error' => $exception->getMessage(),
                ]);
            }

            $serpAnalysis = $this->serpQuestionAnalyzer->analyze($topicResearch, $serpResult, $suggestions);

            $topicResearch
                ->setSerpAnalysis($serpAnalysis)
                ->setCurrentStep(null)
                ->setStatus(PipelineStatus::SERP_ANALYZED);

            $this->entityManager->persist($serpAnalysis);
            $this->entityManager->flush();
            $this->pipelineService->dispatchNext($topicResearch, TopicResearch::STEP_SERP_ANALYSIS);
        } catch (\Throwable $exception) {
            $topicResearch->markFailed(TopicResearch::STEP_SERP_ANALYSIS, $exception->getMessage());
            $this->entityManager->flush();
            $this->logger->error('Pipeline SERP step failed.', [
                'topic_research_id' => $topicResearch->getId(),
                'exception' => $exception,
            ]);

            throw $exception;
        }
    }
}
