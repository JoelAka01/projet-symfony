<?php

declare(strict_types=1);

namespace App\Service\Crawler;

final class CrawlerUrlNormalizer
{
    /** @var list<string> */
    private const NON_HTML_EXTENSIONS = [
        '7z',
        'avi',
        'bmp',
        'bz2',
        'css',
        'csv',
        'doc',
        'docx',
        'eot',
        'flv',
        'gif',
        'gz',
        'ico',
        'jpeg',
        'jpg',
        'js',
        'json',
        'm4a',
        'm4v',
        'mov',
        'mp3',
        'mp4',
        'mpeg',
        'mpg',
        'ogg',
        'otf',
        'pdf',
        'png',
        'ppt',
        'pptx',
        'rar',
        'rss',
        'svg',
        'tar',
        'tgz',
        'tif',
        'tiff',
        'ttf',
        'wav',
        'webm',
        'webp',
        'woff',
        'woff2',
        'xls',
        'xlsx',
        'xml',
        'zip',
    ];

    public function normalizeStartUrl(string $url): ?string
    {
        $candidate = trim($url);
        if ('' === $candidate) {
            return null;
        }

        if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $candidate)) {
            $candidate = 'https://'.$candidate;
        }

        return $this->normalizeAbsoluteUrl($candidate, null, true);
    }

    public function normalizeForCrawl(string $url, string $baseUrl, string $startHostname): ?string
    {
        $absoluteUrl = $this->resolveUrl($url, $baseUrl);
        if (null === $absoluteUrl) {
            return null;
        }

        return $this->normalizeAbsoluteUrl($absoluteUrl, $startHostname, true);
    }

    public function normalizeHttpUrl(string $url, string $baseUrl): ?string
    {
        $absoluteUrl = $this->resolveUrl($url, $baseUrl);
        if (null === $absoluteUrl) {
            return null;
        }

        return $this->normalizeAbsoluteUrl($absoluteUrl, null, false);
    }

    public function getHostname(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host)) {
            return null;
        }

        return $this->normalizeHostname($host);
    }

    public function isSameHostname(string $url, string $hostname): bool
    {
        return $this->getHostname($url) === $this->normalizeHostname($hostname);
    }

    private function resolveUrl(string $url, string $baseUrl): ?string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5));
        if ('' === $url || str_starts_with($url, '#')) {
            return null;
        }

        $baseParts = parse_url($baseUrl);
        if (false === $baseParts || !isset($baseParts['scheme'], $baseParts['host'])) {
            return null;
        }

        if (str_starts_with($url, '//')) {
            return strtolower((string) $baseParts['scheme']).':'.$url;
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $url)) {
            return $url;
        }

        $authority = strtolower((string) $baseParts['scheme']).'://'.$baseParts['host'];
        if (isset($baseParts['port'])) {
            $authority .= ':'.$baseParts['port'];
        }

        if (str_starts_with($url, '/')) {
            return $authority.$url;
        }

        $basePath = $baseParts['path'] ?? '/';
        $baseDir = preg_replace('#/[^/]*$#', '/', $basePath);
        if (!is_string($baseDir) || '' === $baseDir) {
            $baseDir = '/';
        }

        return $authority.$baseDir.$url;
    }

    private function normalizeAbsoluteUrl(string $url, ?string $requiredHostname, bool $filterNonHtmlAssets): ?string
    {
        $parts = parse_url($url);
        if (false === $parts || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $scheme = strtolower((string) $parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $host = $this->normalizeHostname((string) $parts['host']);
        if ('' === $host || $this->isBlockedHost($host)) {
            return null;
        }

        if (null !== $requiredHostname && $host !== $this->normalizeHostname($requiredHostname)) {
            return null;
        }

        $path = $parts['path'] ?? '/';
        if ('' === $path) {
            $path = '/';
        }

        if ($filterNonHtmlAssets && $this->hasNonHtmlAssetExtension($path)) {
            return null;
        }

        $port = $parts['port'] ?? null;
        $authority = $host;
        if (is_int($port) && !$this->isDefaultPort($scheme, $port)) {
            $authority .= ':'.$port;
        }

        $normalizedUrl = $scheme.'://'.$authority.$this->normalizePath($path);
        if (isset($parts['query']) && '' !== $parts['query']) {
            $normalizedUrl .= '?'.$parts['query'];
        }

        if (strlen($normalizedUrl) > 1000) {
            return null;
        }

        return $normalizedUrl;
    }

    private function normalizeHostname(string $host): string
    {
        $host = strtolower(rtrim(trim($host, " \t\n\r\0\x0B[]"), '.'));

        if (function_exists('idn_to_ascii')) {
            $asciiHost = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if (is_string($asciiHost)) {
                $host = strtolower($asciiHost);
            }
        }

        return $host;
    }

    private function isBlockedHost(string $host): bool
    {
        if ('' === $host || 'localhost' === $host || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            return true;
        }

        if (false !== filter_var($host, FILTER_VALIDATE_IP)) {
            return false === filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        return !str_contains($host, '.');
    }

    private function hasNonHtmlAssetExtension(string $path): bool
    {
        $extension = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION));

        return '' !== $extension && in_array($extension, self::NON_HTML_EXTENSIONS, true);
    }

    private function isDefaultPort(string $scheme, int $port): bool
    {
        return ('http' === $scheme && 80 === $port) || ('https' === $scheme && 443 === $port);
    }

    private function normalizePath(string $path): string
    {
        $segments = explode('/', $path);
        $normalizedSegments = [];

        foreach ($segments as $segment) {
            if ('' === $segment || '.' === $segment) {
                continue;
            }

            if ('..' === $segment) {
                array_pop($normalizedSegments);
                continue;
            }

            $normalizedSegments[] = $segment;
        }

        $normalizedPath = '/'.implode('/', $normalizedSegments);
        if ('/' !== $normalizedPath && str_ends_with($path, '/')) {
            $normalizedPath .= '/';
        }

        return $normalizedPath;
    }
}
