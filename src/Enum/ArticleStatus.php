<?php

declare(strict_types=1);

namespace App\Enum;

enum ArticleStatus: string
{
    case DRAFT = 'DRAFT';
    case GENERATED = 'GENERATED';
    case SCHEDULED = 'SCHEDULED';
    case PUBLISHED = 'PUBLISHED';
    case FAILED = 'FAILED';
}
