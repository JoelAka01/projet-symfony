<?php

declare(strict_types=1);

namespace App\Enum;

enum UserRole: string
{
    case OWNER = 'OWNER';
    case ADMIN = 'ADMIN';
    case EDITOR = 'EDITOR';
    case VIEWER = 'VIEWER';
}
