<?php

declare(strict_types=1);

namespace App\Enum;

enum ProjectGuestAccess: string
{
    case CONTENT = 'content';
    case FULL = 'full';

    public function label(): string
    {
        return match ($this) {
            self::CONTENT => 'Content only',
            self::FULL => 'Full access (no delete)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::CONTENT => 'View the project, download reports, and manage articles in the Content studio.',
            self::FULL => 'Edit the project, launch audits, manage CMS connections, and all other actions except deleting the project.',
        };
    }
}