<?php

declare(strict_types=1);

namespace App\Dto\Cms;

final readonly class FetchedImage
{
    public function __construct(
        public string $contents,
        public string $contentType,
        public string $filename,
    ) {}
}
