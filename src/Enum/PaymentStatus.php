<?php

declare(strict_types=1);

namespace App\Enum;

enum PaymentStatus: string
{
    case PAID = 'PAID';
    case REFUNDED = 'REFUNDED';
    case CANCELED = 'CANCELED';
}
