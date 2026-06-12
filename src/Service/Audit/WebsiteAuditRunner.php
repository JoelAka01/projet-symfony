<?php

declare(strict_types=1);

namespace App\Service\Audit;

use App\Entity\Audit;
use App\Entity\Domain;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\AuditStatus;
use App\Service\Ai\ClaudeSeoAnalysisService;
use App\Service\Billing\AnalysisQuotaManager;
use App\Service\Crawler\WebsiteCrawlerService;
use Doctrine\ORM\EntityManagerInterface;

final class WebsiteAuditRunner
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly WebsiteCrawlerService $crawler,
        private readonly ClaudeSeoAnalysisService $claudeSeoAnalysis,
        private readonly AnalysisQuotaManager $quotaManager,
    ) {}

    public function createQueued(Project $project, Domain $domain, User $requestedBy, ?string $clientIp = null): Audit
    {
        $audit = new Audit();
        $audit
            ->setProject($project)
            ->setDomain($domain)
            ->setRequestedBy($requestedBy)
            ->setStatus(AuditStatus::QUEUED)
            ->setMaxPages($this->crawler->getConfiguredMaxPages())
            ->setMaxDepth($this->crawler->getConfiguredMaxDepth())
            ->setMetadata([
                'ai_analysis' => [
                    'status' => 'queued',
                    'provider' => 'anthropic',
                    'recommendations' => [],
                    'queued_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                ],
            ]);

        $this->entityManager->persist($audit);
        $this->quotaManager->reserve($audit, $requestedBy, $clientIp);
        $this->entityManager->flush();

        return $audit;
    }

    public function run(Audit $audit): void
    {
        try {
            $this->crawler->crawl($audit);

            if (AuditStatus::COMPLETED === $audit->getStatus()) {
                $this->claudeSeoAnalysis->analyze($audit);
            } else {
                $this->quotaManager->release($audit);
                $this->entityManager->flush();
            }
        } catch (\Throwable $exception) {
            $this->quotaManager->release($audit);
            $audit
                ->setStatus(AuditStatus::FAILED)
                ->setErrorMessage($exception->getMessage())
                ->setCrawlFinishedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
        }
    }
}
