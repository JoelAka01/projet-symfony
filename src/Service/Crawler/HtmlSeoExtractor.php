<?php

declare(strict_types=1);

namespace App\Service\Crawler;

final class HtmlSeoExtractor
{
    private const STOP_WORDS = [
        'about' => true,
        'after' => true,
        'again' => true,
        'ainsi' => true,
        'also' => true,
        'avec' => true,
        'aux' => true,
        'avoir' => true,
        'before' => true,
        'being' => true,
        'bien' => true,
        'but' => true,
        'can' => true,
        'ces' => true,
        'chez' => true,
        'com' => true,
        'comment' => true,
        'dans' => true,
        'des' => true,
        'does' => true,
        'dont' => true,
        'elle' => true,
        'elles' => true,
        'est' => true,
        'etre' => true,
        'for' => true,
        'from' => true,
        'has' => true,
        'have' => true,
        'how' => true,
        'ils' => true,
        'les' => true,
        'leur' => true,
        'mais' => true,
        'more' => true,
        'not' => true,
        'nous' => true,
        'our' => true,
        'par' => true,
        'pas' => true,
        'plus' => true,
        'pour' => true,
        'que' => true,
        'qui' => true,
        'sans' => true,
        'sur' => true,
        'the' => true,
        'their' => true,
        'this' => true,
        'une' => true,
        'vous' => true,
        'with' => true,
        'your' => true,
    ];

    public function __construct(private readonly CrawlerUrlNormalizer $urlNormalizer)
    {
    }

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
        $metaKeywords = $this->firstAttribute(
            $xpath,
            "//meta[translate(@name, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz') = 'keywords']",
            'content',
        );

        if (null !== $canonicalUrl) {
            $canonicalUrl = $this->urlNormalizer->normalizeHttpUrl($canonicalUrl, $url);
        }

        $h1Headings = $this->texts($xpath, '//h1');
        $h2Headings = $this->texts($xpath, '//h2');
        $h3Headings = $this->texts($xpath, '//h3');
        $h4Headings = $this->texts($xpath, '//h4');
        [$internalLinks, $externalLinks] = $this->extractLinks($xpath, $url, $startHostname);
        $visibleText = $this->visibleText($document);
        $schemaTypes = $this->structuredDataTypes($xpath);
        $imagesWithoutAltCount = $this->countImagesWithoutAlt($xpath);
        $imageCount = $this->countNodes($xpath, '//img');
        $bodyExcerpt = $this->limit($visibleText, 2400);

        return new HtmlSeoExtractionResult(
            $title,
            $metaDescription,
            $h1Headings,
            $h2Headings,
            $canonicalUrl,
            $robotsMeta,
            $this->countWords($visibleText),
            $internalLinks,
            $externalLinks,
            $imagesWithoutAltCount,
            [] !== $schemaTypes,
            [
                'analyzed_url' => $url,
                'language' => $this->language($document),
                'meta_keywords' => $metaKeywords,
                'viewport_meta_present' => null !== $this->firstAttribute(
                    $xpath,
                    "//meta[translate(@name, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz') = 'viewport']",
                    'content',
                ),
                'title_length' => null === $title ? 0 : strlen($title),
                'meta_description_length' => null === $metaDescription ? 0 : strlen($metaDescription),
                'headings' => $this->headings($xpath),
                'h2_headings' => array_slice($h2Headings, 0, 20),
                'h3_headings' => array_slice($h3Headings, 0, 20),
                'h4_headings' => array_slice($h4Headings, 0, 20),
                'image_count' => $imageCount,
                'images_without_alt_count' => $imagesWithoutAltCount,
                'images_with_alt_count' => max(0, $imageCount - $imagesWithoutAltCount),
                'paragraph_count' => $this->countNodes($xpath, '//p[normalize-space()]'),
                'list_count' => $this->countNodes($xpath, '//ul|//ol'),
                'structured_data_types' => $schemaTypes,
                'open_graph' => $this->prefixedMetaMap($xpath, ['og:']),
                'twitter' => $this->prefixedMetaMap($xpath, ['twitter:']),
                'body_excerpt' => '' === $bodyExcerpt ? null : $bodyExcerpt,
                'top_terms' => $this->topTerms($visibleText),
                'html_size_bytes' => strlen($html),
            ],
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

    private function countWords(string $text): int
    {
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

    private function countNodes(\DOMXPath $xpath, string $query): int
    {
        $nodes = $xpath->query($query);

        return false === $nodes ? 0 : $nodes->length;
    }

    private function visibleText(\DOMDocument $document): string
    {
        $clone = clone $document;
        $xpath = new \DOMXPath($clone);
        $nodes = $xpath->query('//script|//style|//noscript|//svg');
        if (false !== $nodes) {
            foreach ($nodes as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        $bodyNodes = $xpath->query('//body');
        $body = false === $bodyNodes ? null : $bodyNodes->item(0);
        if ($body instanceof \DOMNode) {
            return $this->normalizeText((string) $body->textContent);
        }

        return $this->normalizeText((string) $clone->textContent);
    }

    private function language(\DOMDocument $document): ?string
    {
        $root = $document->documentElement;
        if (!$root instanceof \DOMElement) {
            return null;
        }

        $language = $this->normalizeText($root->getAttribute('lang') ?: $root->getAttribute('xml:lang'));

        return '' === $language ? null : $language;
    }

    /** @return list<array{level: string, text: string}> */
    private function headings(\DOMXPath $xpath): array
    {
        $nodes = $xpath->query('//h1|//h2|//h3|//h4');
        if (false === $nodes) {
            return [];
        }

        $headings = [];
        foreach ($nodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $text = $this->normalizeText((string) $node->textContent);
            if ('' === $text) {
                continue;
            }

            $headings[] = [
                'level' => strtoupper($node->nodeName),
                'text' => $this->limit($text, 220),
            ];

            if (count($headings) >= 80) {
                break;
            }
        }

        return $headings;
    }

    /** @param list<string> $prefixes */
    private function prefixedMetaMap(\DOMXPath $xpath, array $prefixes): array
    {
        $nodes = $xpath->query('//meta[@content]');
        if (false === $nodes) {
            return [];
        }

        $values = [];
        foreach ($nodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $name = strtolower($node->getAttribute('property') ?: $node->getAttribute('name'));
            foreach ($prefixes as $prefix) {
                if (!str_starts_with($name, $prefix)) {
                    continue;
                }

                $value = $this->normalizeText($node->getAttribute('content'));
                if ('' !== $value) {
                    $values[$name] = $this->limit($value, 280);
                }
            }
        }

        return array_slice($values, 0, 16, true);
    }

    /** @return list<string> */
    private function structuredDataTypes(\DOMXPath $xpath): array
    {
        $nodes = $xpath->query("//script[translate(@type, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz') = 'application/ld+json']");
        if (false === $nodes) {
            return [];
        }

        $types = [];
        foreach ($nodes as $node) {
            $json = trim((string) $node->textContent);
            if ('' === $json) {
                continue;
            }

            try {
                $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                continue;
            }

            $this->collectJsonLdTypes($decoded, $types);
        }

        $types = array_values(array_unique($types));
        sort($types);

        return array_slice($types, 0, 24);
    }

    /** @param list<string> $types */
    private function collectJsonLdTypes(mixed $value, array &$types): void
    {
        if (!is_array($value)) {
            return;
        }

        $type = $value['@type'] ?? null;
        if (is_string($type) && '' !== trim($type)) {
            $types[] = trim($type);
        } elseif (is_array($type)) {
            foreach ($type as $item) {
                if (is_string($item) && '' !== trim($item)) {
                    $types[] = trim($item);
                }
            }
        }

        foreach ($value as $child) {
            if (is_array($child)) {
                $this->collectJsonLdTypes($child, $types);
            }
        }
    }

    /** @return list<array{term: string, count: int}> */
    private function topTerms(string $text): array
    {
        $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
        preg_match_all("/[\p{L}][\p{L}'-]{2,}/u", $text, $matches);

        $counts = [];
        foreach ($matches[0] as $term) {
            $term = trim($term, "'-");
            if (strlen($term) < 3 || isset(self::STOP_WORDS[$term])) {
                continue;
            }

            $counts[$term] = ($counts[$term] ?? 0) + 1;
        }

        arsort($counts);

        $terms = [];
        foreach (array_slice($counts, 0, 20, true) as $term => $count) {
            $terms[] = [
                'term' => (string) $term,
                'count' => (int) $count,
            ];
        }

        return $terms;
    }

    private function normalizeText(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private function limit(string $value, int $maxLength): string
    {
        return strlen($value) > $maxLength ? substr($value, 0, $maxLength) : $value;
    }
}
