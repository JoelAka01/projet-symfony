<?php

declare(strict_types=1);

namespace App\Enum;

enum SubscriptionStatus: string
{
    case ACTIVE = 'ACTIVE';
    case CANCELED = 'CANCELED';
    case EXPIRED = 'EXPIRED';
}
