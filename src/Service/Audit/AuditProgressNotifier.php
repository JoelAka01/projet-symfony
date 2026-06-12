<?php

declare(strict_types=1);

namespace App\Service\Audit;

use App\Entity\Audit;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final class AuditProgressNotifier
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly AuditProgressStatusBuilder $statusBuilder,
        private readonly LoggerInterface $logger,
    ) {
    }


    public function notify(Audit $audit): void
    {
        try {
            $statusData = $this->statusBuilder->build($audit);
            $topic = sprintf('http://seo-geo-ai.local/audits/%s', $audit->getId());

            $update = new Update(
                $topic,
                json_encode($statusData, JSON_THROW_ON_ERROR)
            );

            $this->hub->publish($update);
        } catch (\Throwable $e) {
            $this->logger->warning('Mercure publish failed (non-fatal): {error}', [
                'error' => $e->getMessage(),
                'auditId' => (string) $audit->getId(),
                'class' => $e::class,
            ]);
        }
    }
}

