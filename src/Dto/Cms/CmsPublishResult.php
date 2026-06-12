<?php

declare(strict_types=1);

namespace App\Dto\Cms;

final readonly class CmsPublishResult
{
    /**
     * @param list<string>         $warnings
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $externalId,
        public ?string $externalUrl,
        public array $warnings = [],
        public array $metadata = [],
    ) {}
}
