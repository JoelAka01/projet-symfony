<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\AuditStatus;
use App\Message\RunClaudeAnalysisMessage;
use App\Repository\AuditRepository;
use App\Service\Ai\ClaudeSeoAnalysisService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RunClaudeAnalysisMessageHandler
{
    public function __construct(
        private readonly AuditRepository $auditRepository,
        private readonly ClaudeSeoAnalysisService $claudeSeoAnalysis,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(RunClaudeAnalysisMessage $message): void
    {
        $audit = $this->auditRepository->find($message->getAuditId());
        if (null === $audit) {
            $this->logger->warning('Queued Claude analysis message ignored because the audit no longer exists.', [
                'audit_id' => $message->getAuditId(),
            ]);

            return;
        }

        if (AuditStatus::COMPLETED !== $audit->getStatus()) {
            $this->logger->info('Queued Claude analysis message ignored because the crawl is not completed.', [
                'audit_id' => $audit->getId(),
                'status' => $audit->getStatus()->value,
            ]);

            return;
        }

        $this->claudeSeoAnalysis->analyze($audit);
    }
}
