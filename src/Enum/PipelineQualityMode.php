<?php

declare(strict_types=1);

namespace App\Enum;

enum PipelineQualityMode: string
{
    case ECONOMY = 'ECONOMY_MODE';
    case BALANCED = 'BALANCED_MODE';
    case QUALITY = 'QUALITY_MODE';

    public function label(): string
    {
        return match ($this) {
            self::ECONOMY => 'Economy',
            self::BALANCED => 'Balanced',
            self::QUALITY => 'Quality',
        };
    }

    public function allowsRefresh(): bool
    {
        return self::QUALITY === $this;
    }
}
