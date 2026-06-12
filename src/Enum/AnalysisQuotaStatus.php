<?php

declare(strict_types=1);

namespace App\Enum;

enum AnalysisQuotaStatus: string
{
    case RESERVED = 'RESERVED';
    case CONSUMED = 'CONSUMED';
    case RELEASED = 'RELEASED';
}
