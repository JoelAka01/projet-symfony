<?php

declare(strict_types=1);

namespace App\Service\Crawler;

final class HtmlSeoExtractor
{
    public function __construct(private readonly CrawlerUrlNormalizer $urlNormalizer) {}

    public function extract(string $html, string $url, string $startHostname): HtmlSeoExtractionResult
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $previousUseInternalErrors = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseInternalErrors);

        $xpath = new \DOMXPath($document);

        $title = $this->firstText($xpath, '//title');
        $metaDescription = $this->firstAttribute(
            $xpath,
            "//meta[translate(@name, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz') = 'description']",
            'content',
        );
        $canonicalUrl = $this->firstAttribute(
            $xpath,
            "//link[contains(concat(' ', normalize-space(translate(@rel, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz')), ' '), ' canonical ')]",
            'href',
        );
        $robotsMeta = $this->firstAttribute(
            $xpath,
            "//meta[translate(@name, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz') = 'robots']",
            'content',
        );

        if (null !== $canonicalUrl) {
            $canonicalUrl = $this->urlNormalizer->normalizeHttpUrl($canonicalUrl, $url);
        }

        [$internalLinks, $externalLinks] = $this->extractLinks($xpath, $url, $startHostname);

        return new HtmlSeoExtractionResult(
            $title,
            $metaDescription,
            $this->texts($xpath, '//h1'),
            $this->texts($xpath, '//h2'),
            $canonicalUrl,
            $robotsMeta,
            $this->countWords($document),
            $internalLinks,
            $externalLinks,
            $this->countImagesWithoutAlt($xpath),
            $this->hasStructuredData($xpath),
        );
    }

    private function firstText(\DOMXPath $xpath, string $query): ?string
    {
        $nodes = $xpath->query($query);
        if (false === $nodes || 0 === $nodes->length) {
            return null;
        }

        $text = $this->normalizeText((string) $nodes->item(0)?->textContent);

        return '' === $text ? null : $text;
    }

    private function firstAttribute(\DOMXPath $xpath, string $query, string $attribute): ?string
    {
        $nodes = $xpath->query($query);
        if (false === $nodes || 0 === $nodes->length) {
            return null;
        }

        $node = $nodes->item(0);
        if (!$node instanceof \DOMElement) {
            return null;
        }

        $value = $this->normalizeText($node->getAttribute($attribute));

        return '' === $value ? null : $value;
    }

    /** @return list<string> */
    private function texts(\DOMXPath $xpath, string $query): array
    {
        $nodes = $xpath->query($query);
        if (false === $nodes) {
            return [];
        }

        $texts = [];
        foreach ($nodes as $node) {
            $text = $this->normalizeText((string) $node->textContent);
            if ('' !== $text) {
                $texts[] = $text;
            }
        }

        return $texts;
    }

    /** @return array{0: list<string>, 1: list<string>} */
    private function extractLinks(\DOMXPath $xpath, string $url, string $startHostname): array
    {
        $nodes = $xpath->query('//a[@href]');
        if (false === $nodes) {
            return [[], []];
        }

        $internalLinks = [];
        $externalLinks = [];

        foreach ($nodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $href = $node->getAttribute('href');
            $crawlUrl = $this->urlNormalizer->normalizeForCrawl($href, $url, $startHostname);
            if (null !== $crawlUrl) {
                $internalLinks[$crawlUrl] = true;
                continue;
            }

            $externalUrl = $this->urlNormalizer->normalizeHttpUrl($href, $url);
            if (null !== $externalUrl && !$this->urlNormalizer->isSameHostname($externalUrl, $startHostname)) {
                $externalLinks[$externalUrl] = true;
            }
        }

        return [array_keys($internalLinks), array_keys($externalLinks)];
    }

    private function countWords(\DOMDocument $document): int
    {
        $clone = clone $document;
        $xpath = new \DOMXPath($clone);
        $nodes = $xpath->query('//script|//style|//noscript');
        if (false !== $nodes) {
            foreach ($nodes as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        $text = $this->normalizeText((string) $clone->textContent);
        if ('' === $text) {
            return 0;
        }

        preg_match_all("/[\p{L}\p{N}][\p{L}\p{N}'-]*/u", $text, $matches);

        return count($matches[0]);
    }

    private function countImagesWithoutAlt(\DOMXPath $xpath): int
    {
        $nodes = $xpath->query('//img[not(@alt) or normalize-space(@alt) = ""]');

        return false === $nodes ? 0 : $nodes->length;
    }

    private function hasStructuredData(\DOMXPath $xpath): bool
    {
        $nodes = $xpath->query("//script[translate(@type, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz') = 'application/ld+json']");

        return false !== $nodes && $nodes->length > 0;
    }

    private function normalizeText(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}
