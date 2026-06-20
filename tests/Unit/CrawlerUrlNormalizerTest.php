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

    public function testNormalizeStartUrlAddsHttpsScheme(): void
    {
        $normalizer = new CrawlerUrlNormalizer();
        self::assertSame('https://example.com/', $normalizer->normalizeStartUrl('example.com'));
        self::assertSame('https://example.com/', $normalizer->normalizeStartUrl('  example.com  '));
        self::assertNull($normalizer->normalizeStartUrl(''));
    }

    public function testNormalizeHttpUrl(): void
    {
        $normalizer = new CrawlerUrlNormalizer();
        self::assertSame('https://example.com/about', $normalizer->normalizeHttpUrl('/about', 'https://example.com/'));
        self::assertNull($normalizer->normalizeHttpUrl('/about', 'invalid-base-url'));
    }

    public function testIsSameHostnameAndGetHostname(): void
    {
        $normalizer = new CrawlerUrlNormalizer();
        self::assertTrue($normalizer->isSameHostname('https://example.com/path', 'example.com'));
        self::assertTrue($normalizer->isSameHostname('https://example.com/path', 'EXAMPLE.COM'));
        self::assertFalse($normalizer->isSameHostname('https://other.com/', 'example.com'));
        self::assertNull($normalizer->getHostname('invalid-url-no-host'));
    }

    public function testResolveProtocolRelativeUrl(): void
    {
        $normalizer = new CrawlerUrlNormalizer();
        self::assertSame(
            'https://other.com/page',
            $normalizer->normalizeForCrawl('//other.com/page', 'https://example.com/', 'other.com'),
        );
    }

    public function testResolveAbsoluteSchemeUrl(): void
    {
        $normalizer = new CrawlerUrlNormalizer();
        self::assertSame(
            'https://example.com/page',
            $normalizer->normalizeForCrawl('https://example.com/page', 'https://other.com/', 'example.com'),
        );
    }

    public function testResolvePathRelativeUrl(): void
    {
        $normalizer = new CrawlerUrlNormalizer();
        self::assertSame(
            'https://example.com/blog/sub/page',
            $normalizer->normalizeForCrawl('sub/page', 'https://example.com/blog/post', 'example.com'),
        );
    }

    public function testBlockedHostsRange(): void
    {
        $normalizer = new CrawlerUrlNormalizer();
        self::assertNull($normalizer->normalizeStartUrl('http://192.168.1.1/'));
        self::assertSame('https://8.8.8.8/', $normalizer->normalizeStartUrl('8.8.8.8'));
        self::assertNull($normalizer->normalizeStartUrl('example')); // no dot, blocked
    }

    public function testUrlLengthLimit(): void
    {
        $normalizer = new CrawlerUrlNormalizer();
        $longUrl = 'https://example.com/' . str_repeat('a', 1000);
        self::assertNull($normalizer->normalizeStartUrl($longUrl));
    }

    public function testNonDefaultPorts(): void
    {
        $normalizer = new CrawlerUrlNormalizer();
        self::assertSame('https://example.com:8443/', $normalizer->normalizeStartUrl('https://example.com:8443/'));
        // default port should be stripped
        self::assertSame('https://example.com/', $normalizer->normalizeStartUrl('https://example.com:443/'));
        self::assertSame('http://example.com/', $normalizer->normalizeStartUrl('http://example.com:80/'));
    }
}
