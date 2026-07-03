<?php

declare(strict_types=1);

namespace App\Message\Pipeline;

final readonly class OptimizeSeoMessage
{
    public function __construct(private string $topicResearchId) {}

    public function getTopicResearchId(): string
    {
        return $this->topicResearchId;
    }
}
