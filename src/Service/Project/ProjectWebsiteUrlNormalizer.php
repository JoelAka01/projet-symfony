<?php

declare(strict_types=1);

namespace App\Service\Project;

use App\Exception\InvalidWebsiteUrlException;
use App\Service\Crawler\CrawlerUrlNormalizer;

final class ProjectWebsiteUrlNormalizer
{
    public function __construct(private readonly CrawlerUrlNormalizer $urlNormalizer)
    {
    }

    public function normalize(string $websiteUrl): ?string
    {
        return $this->urlNormalizer->normalizeStartUrl($websiteUrl);
    }

    public function requireValid(string $websiteUrl): string
    {
        $normalizedUrl = $this->normalize($websiteUrl);
        if (null === $normalizedUrl) {
            throw new InvalidWebsiteUrlException('Enter a public HTTP(S) website URL. Localhost, private IPs, non-HTTP schemes, and asset files cannot be used as project websites.');
        }

        if (strlen($normalizedUrl) > 255) {
            throw new InvalidWebsiteUrlException('The normalized website URL must be 255 characters or fewer.');
        }

        return $normalizedUrl;
    }
}
