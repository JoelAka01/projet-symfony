<?php

declare(strict_types=1);

namespace App\Enum;

enum BacklinkStatus: string
{
    case PROPOSED = 'PROPOSED';
    case ACCEPTED = 'ACCEPTED';
    case PLACED = 'PLACED';
    case BROKEN = 'BROKEN';
    case REMOVED = 'REMOVED';
    case REJECTED = 'REJECTED';
}
