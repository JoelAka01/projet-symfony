<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\Crawler\CrawlerUrlNormalizer;
use PHPUnit\Framework\TestCase;

final class CrawlerUrlNormalizerTest extends TestCase
{
    public function testItNormalizesRelativeUrlsAndKeepsSameHostname(): void
    {
        $normalizer = new CrawlerUrlNormalizer();

        self::assertSame(
            'https://example.com/guides/seo?page=1',
            $normalizer->normalizeForCrawl('../guides/seo?page=1#intro', 'https://example.com/blog/post', 'example.com'),
        );
    }

    public function testItRejectsDifferentHostnames(): void
    {
        $normalizer = new CrawlerUrlNormalizer();

        self::assertNull(
            $normalizer->normalizeForCrawl('https://other.example.com/page', 'https://example.com/', 'example.com'),
        );
    }

    public function testItRejectsLocalhostAndPrivateIpTargets(): void
    {
        $normalizer = new CrawlerUrlNormalizer();

        self::assertNull($normalizer->normalizeStartUrl('http://localhost/'));
        self::assertNull($normalizer->normalizeStartUrl('http://127.0.0.1/'));
        self::assertNull($normalizer->normalizeStartUrl('http://10.0.0.8/'));
        self::assertNull($normalizer->normalizeStartUrl('http://[::1]/'));
    }

    public function testItRejectsNonHttpSchemes(): void
    {
        $normalizer = new CrawlerUrlNormalizer();

        self::assertNull($normalizer->normalizeForCrawl('mailto:test@example.com', 'https://example.com/', 'example.com'));
        self::assertNull($normalizer->normalizeForCrawl('file:///etc/passwd', 'https://example.com/', 'example.com'));
    }

    public function testItRejectsNonHtmlAssetUrls(): void
    {
        $normalizer = new CrawlerUrlNormalizer();

        self::assertNull($normalizer->normalizeForCrawl('/assets/app.css', 'https://example.com/', 'example.com'));
        self::assertNull($normalizer->normalizeForCrawl('/report.pdf', 'https://example.com/', 'example.com'));
        self::assertNull($normalizer->normalizeForCrawl('/photo.webp', 'https://example.com/', 'example.com'));
    }
}
