<?php

declare(strict_types=1);

namespace App\Message;

final class RunWebsiteAuditMessage
{
    public function __construct(private readonly string $auditId)
    {
    }

    public function getAuditId(): string
    {
        return $this->auditId;
    }
}
