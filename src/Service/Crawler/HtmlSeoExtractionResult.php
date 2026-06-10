<?php

declare(strict_types=1);

namespace App\Service\Crawler;

final class HtmlSeoExtractionResult
{
    /**
     * @param list<string> $h1Headings
     * @param list<string> $h2Headings
     * @param list<string> $internalLinks
     * @param list<string> $externalLinks
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly ?string $title,
        public readonly ?string $metaDescription,
        public readonly array $h1Headings,
        public readonly array $h2Headings,
        public readonly ?string $canonicalUrl,
        public readonly ?string $robotsMeta,
        public readonly int $wordCount,
        public readonly array $internalLinks,
        public readonly array $externalLinks,
        public readonly int $imagesWithoutAltCount,
        public readonly bool $hasStructuredData,
        public readonly array $metadata = [],
    ) {
    }
}
