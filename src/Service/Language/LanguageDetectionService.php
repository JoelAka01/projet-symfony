<?php

declare(strict_types=1);

namespace App\Service\Language;

use App\Dto\LanguageDetectionResult;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Detects the primary language and country of a website by analysing its homepage.
 *
 * Detection priority:
 *   1. HTML lang attribute           → confidence 95
 *   2. Content-Language HTTP header   → confidence 85
 *   3. hreflang link elements        → confidence 80
 *   4. TLD-based inference (.fr etc.) → confidence 60
 *   5. No signal detected             → confidence 0
 */
final class LanguageDetectionService
{
    private const TIMEOUT_SECONDS = 8;
    private const USER_AGENT = 'SEO-GEO-LanguageDetector/1.0';

    /** @var array<string, string> TLD → ISO-639-1 language code */
    private const TLD_LANGUAGE_MAP = [
        'fr' => 'fr',
        'de' => 'de',
        'es' => 'es',
        'it' => 'it',
        'pt' => 'pt',
        'nl' => 'nl',
        'be' => 'fr',
        'ch' => 'de',
        'at' => 'de',
        'uk' => 'en',
        'au' => 'en',
        'ca' => 'en',
        'mx' => 'es',
        'br' => 'pt',
        'ar' => 'es',
        'cl' => 'es',
        'co' => 'es',
        'jp' => 'ja',
        'kr' => 'ko',
        'cn' => 'zh',
        'ru' => 'ru',
        'pl' => 'pl',
        'se' => 'sv',
        'no' => 'no',
        'dk' => 'da',
        'fi' => 'fi',
    ];

    /** @var array<string, string> TLD → ISO-3166-1 alpha-2 country code */
    private const TLD_COUNTRY_MAP = [
        'fr' => 'FR',
        'de' => 'DE',
        'es' => 'ES',
        'it' => 'IT',
        'pt' => 'PT',
        'nl' => 'NL',
        'be' => 'BE',
        'ch' => 'CH',
        'at' => 'AT',
        'uk' => 'GB',
        'au' => 'AU',
        'ca' => 'CA',
        'mx' => 'MX',
        'br' => 'BR',
        'ar' => 'AR',
        'cl' => 'CL',
        'co' => 'CO',
        'jp' => 'JP',
        'kr' => 'KR',
        'cn' => 'CN',
        'ru' => 'RU',
        'pl' => 'PL',
        'se' => 'SE',
        'no' => 'NO',
        'dk' => 'DK',
        'fi' => 'FI',
        'lu' => 'LU',
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {}

    public function detect(string $url): LanguageDetectionResult
    {
        $url = $this->normalizeUrl($url);

        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => self::TIMEOUT_SECONDS,
                'max_duration' => self::TIMEOUT_SECONDS + 5,
                'max_redirects' => 5,
                'headers' => [
                    'User-Agent' => self::USER_AGENT,
                    'Accept' => 'text/html',
                    'Accept-Language' => '*',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                $this->logger->warning('Language detection: homepage returned HTTP {status}.', [
                    'url' => $url,
                    'status' => $statusCode,
                ]);

                return $this->fallbackFromTld($url);
            }

            $headers = $response->getHeaders(false);
            $html = $response->getContent(false);

            // Priority 1: HTML lang attribute
            $result = $this->detectFromHtmlLang($html, $url);
            if (null !== $result) {
                return $result;
            }

            // Priority 2: Content-Language HTTP header
            $result = $this->detectFromContentLanguageHeader($headers, $url);
            if (null !== $result) {
                return $result;
            }

            // Priority 3: hreflang link elements
            $result = $this->detectFromHreflang($html, $url);
            if (null !== $result) {
                return $result;
            }

            // Priority 4: TLD fallback
            return $this->fallbackFromTld($url);
        } catch (\Throwable $exception) {
            $this->logger->warning('Language detection failed for URL.', [
                'url' => $url,
                'error' => $exception->getMessage(),
            ]);

            return $this->fallbackFromTld($url);
        }
    }

    private function detectFromHtmlLang(string $html, string $url): ?LanguageDetectionResult
    {
        // Match <html lang="..."> or <html xml:lang="...">
        if (preg_match('/<html[^>]*\blang=["\']([a-zA-Z]{2,5}(?:-[a-zA-Z]{2,5})?)["\'][^>]*>/i', $html, $matches)) {
            return $this->parseLanguageTag($matches[1], 95, 'html_lang', $url);
        }

        return null;
    }

    /**
     * @param array<string, list<string>> $headers
     */
    private function detectFromContentLanguageHeader(array $headers, string $url): ?LanguageDetectionResult
    {
        $contentLanguage = $headers['content-language'][0] ?? null;
        if (null === $contentLanguage || '' === trim($contentLanguage)) {
            return null;
        }

        // Take the first language if multiple are specified
        $tag = explode(',', $contentLanguage)[0];

        return $this->parseLanguageTag(trim($tag), 85, 'content_language_header', $url);
    }

    private function detectFromHreflang(string $html, string $url): ?LanguageDetectionResult
    {
        // Find hreflang attributes in link elements
        if (preg_match_all('/<link[^>]*hreflang=["\']([a-zA-Z]{2}(?:-[a-zA-Z]{2})?)["\'][^>]*>/i', $html, $matches)) {
            $hreflangs = array_unique(array_map('strtolower', $matches[1]));

            // Remove x-default
            $hreflangs = array_filter($hreflangs, static fn(string $lang): bool => 'x-default' !== $lang);

            if ([] === $hreflangs) {
                return null;
            }

            // Use the first hreflang as the primary language
            $primary = reset($hreflangs);

            return $this->parseLanguageTag($primary, 80, 'hreflang', $url);
        }

        return null;
    }

    private function fallbackFromTld(string $url): LanguageDetectionResult
    {
        $tld = $this->extractTld($url);
        if (null !== $tld && isset(self::TLD_LANGUAGE_MAP[$tld])) {
            return new LanguageDetectionResult(
                language: self::TLD_LANGUAGE_MAP[$tld],
                country: self::TLD_COUNTRY_MAP[$tld],
                confidence: 60,
                detectionMethod: 'tld',
            );
        }

        return new LanguageDetectionResult(
            language: null,
            country: null,
            confidence: 0,
            detectionMethod: 'none',
        );
    }

    private function parseLanguageTag(string $tag, int $confidence, string $method, string $url): LanguageDetectionResult
    {
        $tag = strtolower(trim($tag));
        $parts = preg_split('/[-_]/', $tag, 2);
        if (false === $parts || [] === $parts) {
            return $this->fallbackFromTld($url);
        }

        $language = $parts[0];
        $country = isset($parts[1]) ? strtoupper($parts[1]) : null;

        // If no country from the tag, try to infer from TLD
        if (null === $country) {
            $tld = $this->extractTld($url);
            if (null !== $tld && isset(self::TLD_COUNTRY_MAP[$tld])) {
                $country = self::TLD_COUNTRY_MAP[$tld];
            }
        }

        return new LanguageDetectionResult(
            language: $language,
            country: $country,
            confidence: $confidence,
            detectionMethod: $method,
        );
    }

    private function extractTld(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || '' === $host) {
            return null;
        }

        $parts = explode('.', $host);
        $tld = end($parts);

        return strtolower($tld);
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            $url = 'https://' . $url;
        }

        // Ensure we're hitting the homepage
        $parsed = parse_url($url);
        if (is_array($parsed) && isset($parsed['host'])) {
            $scheme = $parsed['scheme'] ?? 'https';
            $host = $parsed['host'];
            $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';

            return $scheme . '://' . $host . $port . '/';
        }

        return $url;
    }
}
