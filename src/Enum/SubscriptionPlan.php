<?php

declare(strict_types=1);

namespace App\Enum;

enum SubscriptionPlan: string
{
    case STARTER = 'STARTER';
    case PRO = 'PRO';
    case EXPERT = 'EXPERT';

    public function label(): string
    {
        return ucfirst(strtolower($this->value));
    }
}
