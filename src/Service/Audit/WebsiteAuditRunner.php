<?php

declare(strict_types=1);

namespace App\Service\Audit;

use App\Entity\Audit;
use App\Entity\Domain;
use App\Entity\Project;
use App\Enum\AuditStatus;
use App\Service\Ai\ClaudeSeoAnalysisService;
use App\Service\Crawler\WebsiteCrawlerService;
use Doctrine\ORM\EntityManagerInterface;

final class WebsiteAuditRunner
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly WebsiteCrawlerService $crawler,
        private readonly ClaudeSeoAnalysisService $claudeSeoAnalysis,
    ) {
    }

    public function createQueued(Project $project, Domain $domain): Audit
    {
        $audit = new Audit();
        $audit
            ->setProject($project)
            ->setDomain($domain)
            ->setStatus(AuditStatus::QUEUED)
            ->setMaxPages($this->crawler->getConfiguredMaxPages())
            ->setMaxDepth($this->crawler->getConfiguredMaxDepth())
            ->setMetadata([
                'ai_analysis' => [
                    'status' => 'queued',
                    'provider' => 'anthropic',
                    'recommendations' => [],
                ],
            ]);

        $this->entityManager->persist($audit);
        $this->entityManager->flush();

        return $audit;
    }

    public function run(Audit $audit): void
    {
        try {
            $this->crawler->crawl($audit);

            if (AuditStatus::COMPLETED === $audit->getStatus()) {
                $this->claudeSeoAnalysis->analyze($audit);
            }
        } catch (\Throwable $exception) {
            $audit
                ->setStatus(AuditStatus::FAILED)
                ->setErrorMessage($exception->getMessage())
                ->setCrawlFinishedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
        }
    }
}
