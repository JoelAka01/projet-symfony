<?php

declare(strict_types=1);

namespace App\Service\InternalLinking;

use App\Entity\Project;
use App\Entity\SitePage;
use App\Enum\SitePageType;
use App\Repository\SitePageRepository;

final class InternalLinkValidator
{
    private const BANNED_ANCHORS = [
        'ce produit',
        'cette offre',
        'cliquez ici',
        'en savoir plus',
        'en savoir plus ici',
        'ce lien',
        'cette page',
        'cette page utile',
        'ce service',
    ];
    private const REPETITIVE_PHRASES = [
        'Pour approfondir ce point',
        'Consultez ce produit',
        'Cette offre',
        'Ce lien',
        'En savoir plus ici',
    ];

    public function __construct(private readonly SitePageRepository $sitePageRepository) {}

    /**
     * @param list<SitePage>|null $sitePages
     *
     * @return array{
     *     is_valid: bool,
     *     total_internal_links: int,
     *     unique_internal_urls: int,
     *     has_contact_or_quote: bool,
     *     has_service_product_or_category: bool,
     *     unknown_urls: list<string>,
     *     heading_links: int,
     *     generic_anchors: list<string>,
     *     repetitive_phrases: list<string>,
     *     paragraphs_with_multiple_links: int,
     *     issues: list<string>
     * }
     */
    public function validate(Project $project, string $contentHtml, ?array $sitePages = null): array
    {
        $sitePages ??= $this->sitePageRepository->findActiveForProject($project);
        $known = $this->knownUrlMap($sitePages);
        $knownHosts = $this->knownHosts($sitePages);
        $links = $this->extractLinks($contentHtml);
        $internalUrls = [];
        $unknownUrls = [];
        $headingLinks = 0;
        $genericAnchors = [];
        $hasContactOrQuote = false;
        $hasServiceProductOrCategory = false;
        $paragraphInternalLinkCounts = [];

        foreach ($links as $link) {
            $key = $this->urlKey($link['href']);
            if (null === $key) {
                continue;
            }

            $sitePage = $known[$key] ?? null;
            if ($sitePage instanceof SitePage) {
                $internalUrls[] = $sitePage->getUrl();
                $paragraphKey = $link['paragraph_key'];
                if (null !== $paragraphKey) {
                    $paragraphInternalLinkCounts[$paragraphKey] = ($paragraphInternalLinkCounts[$paragraphKey] ?? 0) + 1;
                }
                if ($this->isBannedAnchor($link['anchor'])) {
                    $genericAnchors[] = $link['anchor'];
                }
                if (in_array($sitePage->getPageType(), [SitePageType::CONTACT, SitePageType::QUOTE], true)) {
                    $hasContactOrQuote = true;
                }
                if (in_array($sitePage->getPageType(), [SitePageType::SERVICE, SitePageType::PRODUCT, SitePageType::CATEGORY], true)) {
                    $hasServiceProductOrCategory = true;
                }
            } elseif ($this->looksInternal($link['href'], $knownHosts)) {
                $unknownUrls[] = $link['href'];
                $paragraphKey = $link['paragraph_key'];
                if (null !== $paragraphKey) {
                    $paragraphInternalLinkCounts[$paragraphKey] = ($paragraphInternalLinkCounts[$paragraphKey] ?? 0) + 1;
                }
            }

            if ($link['in_heading']) {
                ++$headingLinks;
            }
        }

        $issues = [];
        $uniqueInternalUrls = array_values(array_unique($internalUrls));
        $repetitivePhrases = $this->findRepetitivePhrases($contentHtml);
        $paragraphsWithMultipleLinks = count(array_filter(
            $paragraphInternalLinkCounts,
            static fn(int $linkCount): bool => $linkCount > 1,
        ));

        if (count($sitePages) >= 3 && count($uniqueInternalUrls) < 3) {
            $issues[] = 'At least 3 unique internal links are expected when enough pages are active.';
        }
        if ([] !== $unknownUrls) {
            $issues[] = 'Unknown internal URLs were found.';
        }
        if ($headingLinks > 0) {
            $issues[] = 'Internal links must not be placed in headings.';
        }
        if ([] !== $genericAnchors) {
            $issues[] = 'Generic internal link anchors are not allowed.';
        }
        if ([] !== $repetitivePhrases) {
            $issues[] = 'Repetitive internal linking sentences are not allowed.';
        }
        if ($paragraphsWithMultipleLinks > 0) {
            $issues[] = 'Only one internal link per paragraph is allowed.';
        }
        if ($this->hasType($sitePages, SitePageType::CONTACT, SitePageType::QUOTE) && !$hasContactOrQuote) {
            $issues[] = 'A contact or quote link is expected when available.';
        }
        if ($this->hasType($sitePages, SitePageType::SERVICE, SitePageType::PRODUCT, SitePageType::CATEGORY) && !$hasServiceProductOrCategory) {
            $issues[] = 'A service, product, or category link is expected when available.';
        }

        return [
            'is_valid' => [] === $issues,
            'total_internal_links' => count($internalUrls),
            'unique_internal_urls' => count($uniqueInternalUrls),
            'has_contact_or_quote' => $hasContactOrQuote,
            'has_service_product_or_category' => $hasServiceProductOrCategory,
            'unknown_urls' => array_values(array_unique($unknownUrls)),
            'heading_links' => $headingLinks,
            'generic_anchors' => array_values(array_unique($genericAnchors)),
            'repetitive_phrases' => $repetitivePhrases,
            'paragraphs_with_multiple_links' => $paragraphsWithMultipleLinks,
            'issues' => $issues,
        ];
    }

    /**
     * @return list<array{href: string, anchor: string, in_heading: bool, paragraph_key: string|null}>
     */
    private function extractLinks(string $contentHtml): array
    {
        $dom = $this->loadFragment($contentHtml);
        $links = [];
        foreach ($dom->getElementsByTagName('a') as $anchor) {
            $href = trim($anchor->getAttribute('href'));
            if ('' === $href) {
                continue;
            }

            $links[] = [
                'href' => $href,
                'anchor' => trim($anchor->textContent),
                'in_heading' => $this->hasAncestor($anchor, ['h1', 'h2', 'h3']),
                'paragraph_key' => $this->paragraphKey($anchor),
            ];
        }

        return $links;
    }

    private function loadFragment(string $contentHtml): \DOMDocument
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div>' . $contentHtml . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $dom;
    }

    /**
     * @param list<SitePage> $sitePages
     *
     * @return array<string, SitePage>
     */
    private function knownUrlMap(array $sitePages): array
    {
        $known = [];
        foreach ($sitePages as $sitePage) {
            foreach ($this->urlKeys($sitePage->getUrl()) as $key) {
                $known[$key] = $sitePage;
            }
        }

        return $known;
    }

    /** @param list<SitePage> $sitePages */
    private function knownHosts(array $sitePages): array
    {
        $hosts = [];
        foreach ($sitePages as $sitePage) {
            $host = parse_url($sitePage->getUrl(), PHP_URL_HOST);
            if (is_string($host) && '' !== $host) {
                $hosts[] = strtolower($host);
            }
        }

        return array_values(array_unique($hosts));
    }

    /** @return list<string> */
    private function urlKeys(string $url): array
    {
        $key = $this->urlKey($url);
        if (null === $key) {
            return [];
        }

        $keys = [$key];
        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && '' !== $path) {
            $keys[] = $this->normalizePath($path);
        }

        return array_values(array_unique($keys));
    }

    private function urlKey(string $url): ?string
    {
        $url = trim($url);
        if ('' === $url || str_starts_with($url, '#') || str_starts_with($url, 'mailto:') || str_starts_with($url, 'tel:')) {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);
        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($host) && '' !== $host) {
            return strtolower($host) . $this->normalizePath(is_string($path) ? $path : '/');
        }

        return $this->normalizePath(is_string($path) ? $path : $url);
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        if ('/' !== $path && !str_contains(basename($path), '.')) {
            $path = rtrim($path, '/') . '/';
        }

        return strtolower($path);
    }

    /** @param list<string> $knownHosts */
    private function looksInternal(string $href, array $knownHosts): bool
    {
        if (str_starts_with($href, '/')) {
            return true;
        }

        $host = parse_url($href, PHP_URL_HOST);

        return is_string($host) && in_array(strtolower($host), $knownHosts, true);
    }

    /** @param list<string> $tags */
    private function hasAncestor(\DOMNode $node, array $tags): bool
    {
        $parent = $node->parentNode;
        while ($parent instanceof \DOMNode) {
            if ($parent instanceof \DOMElement && in_array(strtolower($parent->tagName), $tags, true)) {
                return true;
            }
            $parent = $parent->parentNode;
        }

        return false;
    }

    private function paragraphKey(\DOMNode $node): ?string
    {
        $parent = $node->parentNode;
        while ($parent instanceof \DOMNode) {
            if ($parent instanceof \DOMElement && 'p' === strtolower($parent->tagName)) {
                return spl_object_hash($parent);
            }
            $parent = $parent->parentNode;
        }

        return null;
    }

    /** @return list<string> */
    private function findRepetitivePhrases(string $contentHtml): array
    {
        $text = html_entity_decode(strip_tags($contentHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $found = [];
        foreach (self::REPETITIVE_PHRASES as $phrase) {
            if (str_contains(mb_strtolower($text), mb_strtolower($phrase))) {
                $found[] = $phrase;
            }
        }

        return $found;
    }

    private function isBannedAnchor(string $anchor): bool
    {
        $normalized = $this->normalizeText($anchor);
        foreach (self::BANNED_ANCHORS as $bannedAnchor) {
            if ($normalized === $this->normalizeText($bannedAnchor)) {
                return true;
            }
        }

        return mb_strlen($normalized) < 8 || str_word_count($normalized) < 2;
    }

    private function normalizeText(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = mb_strtolower(trim($value));

        return (string) preg_replace('/\s+/', ' ', $value);
    }

    /** @param list<SitePage> $sitePages */
    private function hasType(array $sitePages, SitePageType ...$types): bool
    {
        foreach ($sitePages as $sitePage) {
            if (in_array($sitePage->getPageType(), $types, true)) {
                return true;
            }
        }

        return false;
    }
}
