<?php

declare(strict_types=1);

namespace App\Enum;

enum ReportStatus: string
{
    case QUEUED = 'QUEUED';
    case GENERATED = 'GENERATED';
    case SENT = 'SENT';
    case FAILED = 'FAILED';
}
