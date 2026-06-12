<?php

declare(strict_types=1);

namespace App\Enum;

enum ProjectStatus: string
{
    case ACTIVE = 'ACTIVE';
    case PAUSED = 'PAUSED';
}
