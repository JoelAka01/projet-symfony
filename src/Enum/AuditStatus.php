<?php

declare(strict_types=1);

namespace App\Enum;

enum AuditStatus: string
{
    case QUEUED = 'QUEUED';
    case RUNNING = 'RUNNING';
    case COMPLETED = 'COMPLETED';
    case FAILED = 'FAILED';
}
