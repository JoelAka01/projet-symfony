<?php

declare(strict_types=1);

namespace App\Enum;

enum InternalLinkSuggestionStatus: string
{
    case SUGGESTED = 'SUGGESTED';
    case INSERTED = 'INSERTED';
    case REJECTED = 'REJECTED';
    case FAILED = 'FAILED';
}
