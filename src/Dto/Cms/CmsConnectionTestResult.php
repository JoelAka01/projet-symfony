<?php

declare(strict_types=1);

namespace App\Dto\Cms;

final readonly class CmsConnectionTestResult
{
    /** @param array<string, mixed> $details */
    public function __construct(
        public string $message,
        public array $details = [],
    ) {}
}
