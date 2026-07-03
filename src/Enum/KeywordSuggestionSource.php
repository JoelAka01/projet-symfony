<?php

declare(strict_types=1);

namespace App\Enum;

enum KeywordSuggestionSource: string
{
    case AUDIT_DETECTED_KEYWORD = 'AUDIT_DETECTED_KEYWORD';
    case COMPETITOR_FREQUENT_TERM = 'COMPETITOR_FREQUENT_TERM';
    case AUDIT_QUESTION = 'AUDIT_QUESTION';
    case CONTENT_GAP = 'CONTENT_GAP';
    case SERP_SUGGEST = 'SERP_SUGGEST';
    case AI_GENERATED = 'AI_GENERATED';

    public function label(): string
    {
        return match ($this) {
            self::AUDIT_DETECTED_KEYWORD => 'Audit keyword',
            self::COMPETITOR_FREQUENT_TERM => 'Competitor term',
            self::AUDIT_QUESTION => 'Audit question',
            self::CONTENT_GAP => 'Content gap',
            self::SERP_SUGGEST => 'SERP suggest',
            self::AI_GENERATED => 'AI generated',
        };
    }
}
