<?php

declare(strict_types=1);

namespace App\Service\Content;

final class ArticleHtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'strong', 'em', 'blockquote',
        'a', 'figure', 'figcaption', 'img', 'table', 'thead', 'tbody', 'tr', 'th',
        'td', 'code', 'pre', 'hr', 'br',
    ];

    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'title'],
        'img' => ['src', 'alt', 'title', 'loading', 'width', 'height'],
        'th' => ['scope'],
        'td' => ['colspan', 'rowspan'],
    ];

    public function sanitize(string $html): string
    {
        $html = preg_replace(
            '#<(script|style|iframe|object|embed|form|input|button|svg)[^>]*>.*?</\1>#is',
            '',
            $html,
        ) ?? '';
        $html = strip_tags($html, '<' . implode('><', self::ALLOWED_TAGS) . '>');

        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><div data-root="article">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (false === $loaded) {
            return trim(strip_tags($html));
        }

        $xpath = new \DOMXPath($document);
        $nodes = $xpath->query('//*[@data-root="article"]//*');
        if (false !== $nodes) {
            foreach ($nodes as $node) {
                if (!$node instanceof \DOMElement) {
                    continue;
                }

                $allowed = self::ALLOWED_ATTRIBUTES[strtolower($node->tagName)] ?? [];
                $remove = [];
                foreach ($node->attributes as $attribute) {
                    if (!in_array(strtolower($attribute->name), $allowed, true)) {
                        $remove[] = $attribute->name;
                    }
                }

                foreach ($remove as $attributeName) {
                    $node->removeAttribute($attributeName);
                }

                foreach (['href', 'src'] as $urlAttribute) {
                    if ($node->hasAttribute($urlAttribute) && !$this->isSafeUrl($node->getAttribute($urlAttribute))) {
                        $node->removeAttribute($urlAttribute);
                    }
                }
            }
        }

        $rootNodes = $xpath->query('//*[@data-root="article"]');
        $root = false === $rootNodes ? null : $rootNodes->item(0);
        if (!$root instanceof \DOMElement) {
            return '';
        }

        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }

        return trim($result);
    }

    private function isSafeUrl(string $url): bool
    {
        $url = trim($url);
        if ('' === $url || str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return true;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https', 'mailto'], true);
    }
}
