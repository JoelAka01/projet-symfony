<?php

declare(strict_types=1);

namespace App\Enum;

enum GeoProvider: string
{
    case CHATGPT = 'CHATGPT';
    case GEMINI = 'GEMINI';
    case PERPLEXITY = 'PERPLEXITY';
    case CLAUDE = 'CLAUDE';
}
