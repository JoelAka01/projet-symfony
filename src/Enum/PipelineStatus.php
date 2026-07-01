<?php

declare(strict_types=1);

namespace App\Enum;

enum PipelineStatus: string
{
    case NEW = 'NEW';
    case SERP_ANALYZING = 'SERP_ANALYZING';
    case SERP_ANALYZED = 'SERP_ANALYZED';
    case INTELLIGENCE_ANALYZING = 'INTELLIGENCE_ANALYZING';
    case INTELLIGENCE_ANALYZED = 'INTELLIGENCE_ANALYZED';
    case BRIEF_GENERATING = 'BRIEF_GENERATING';
    case BRIEF_READY = 'BRIEF_READY';
    case CONTENT_GENERATING = 'CONTENT_GENERATING';
    case CONTENT_GENERATED = 'CONTENT_GENERATED';
    case INTERNAL_LINKING = 'INTERNAL_LINKING';
    case INTERNAL_LINKED = 'INTERNAL_LINKED';
    case SEO_OPTIMIZING = 'SEO_OPTIMIZING';
    case SEO_OPTIMIZED = 'SEO_OPTIMIZED';
    case READY_TO_PUBLISH = 'READY_TO_PUBLISH';
    case PUBLISHED = 'PUBLISHED';
    case FAILED = 'FAILED';

    public function isRunning(): bool
    {
        return in_array($this, [
            self::SERP_ANALYZING,
            self::INTELLIGENCE_ANALYZING,
            self::BRIEF_GENERATING,
            self::CONTENT_GENERATING,
            self::INTERNAL_LINKING,
            self::SEO_OPTIMIZING,
        ], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::READY_TO_PUBLISH,
            self::PUBLISHED,
            self::FAILED,
        ], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::NEW => 'En attente',
            self::SERP_ANALYZING => 'Analyse SERP',
            self::SERP_ANALYZED => 'SERP analysee',
            self::INTELLIGENCE_ANALYZING => 'Analyse intention',
            self::INTELLIGENCE_ANALYZED => 'Intelligence prete',
            self::BRIEF_GENERATING => 'Brief en cours',
            self::BRIEF_READY => 'Brief pret',
            self::CONTENT_GENERATING => 'Article en cours',
            self::CONTENT_GENERATED => 'Article genere',
            self::INTERNAL_LINKING => 'Maillage interne',
            self::INTERNAL_LINKED => 'Maillage interne pret',
            self::SEO_OPTIMIZING => 'Score SEO en cours',
            self::SEO_OPTIMIZED => 'Score SEO pret',
            self::READY_TO_PUBLISH => 'Pret a publier',
            self::PUBLISHED => 'Publie',
            self::FAILED => 'Echec',
        };
    }
}
