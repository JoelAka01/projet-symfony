<?php

declare(strict_types=1);

namespace App\Dto;

final readonly class PipelineClaudeResult
{
    /**
     * @param array<string, mixed> $parsedResponse
     * @param array<string, mixed> $usage
     */
    public function __construct(
        public array $parsedResponse,
        public string $rawResponse,
        public array $usage,
        public string $model,
    ) {}
}
