<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\AuditStatus;
use App\Message\RunWebsiteAuditMessage;
use App\Repository\AuditRepository;
use App\Service\Audit\WebsiteAuditRunner;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RunWebsiteAuditMessageHandler
{
    public function __construct(
        private readonly AuditRepository $auditRepository,
        private readonly WebsiteAuditRunner $auditRunner,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(RunWebsiteAuditMessage $message): void
    {
        $audit = $this->auditRepository->find($message->getAuditId());
        if (null === $audit) {
            $this->logger->warning('Queued audit message ignored because the audit no longer exists.', [
                'audit_id' => $message->getAuditId(),
            ]);

            return;
        }

        if (!in_array($audit->getStatus(), [AuditStatus::QUEUED, AuditStatus::RUNNING], true)) {
            $this->logger->info('Queued audit message ignored because the audit already finished.', [
                'audit_id' => $audit->getId(),
                'status' => $audit->getStatus()->value,
            ]);

            return;
        }

        $this->auditRunner->run($audit);
    }
}
