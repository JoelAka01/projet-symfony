<?php

declare(strict_types=1);

namespace App\Service\InternalLinking;

use App\Entity\Article;
use App\Entity\InternalLinkSuggestion;
use App\Entity\Project;
use App\Entity\SitePage;
use App\Enum\InternalLinkSuggestionStatus;
use App\Enum\SitePageType;
use App\Repository\InternalLinkSuggestionRepository;
use App\Repository\SitePageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class InternalLinkingService
{
    private const MIN_LINKS = 3;
    private const MAX_LINKS = 8;
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
    private const CONVERSION_TERMS = [
        'devis',
        'contact',
        'contacter',
        'demander',
        'réserver',
        'reserver',
        'besoin',
        'projet',
        'accompagnement',
        'conseil',
    ];
    private const PRICING_TERMS = [
        'tarif',
        'tarifs',
        'prix',
        'forfait',
        'abonnement',
        'budget',
        '€',
        'eur',
        'ht',
        'ttc',
    ];

    public function __construct(
        private readonly SitePageRepository $sitePageRepository,
        private readonly InternalLinkSuggestionRepository $suggestionRepository,
        private readonly InternalLinkValidator $validator,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function apply(Article $article, Project $project, string $contentHtml): array
    {
        $contentHtml = $this->stripRepetitiveLinkSentences($contentHtml);
        $sitePages = $this->sitePageRepository->findActiveForProject($project);
        if ([] === $sitePages) {
            $validation = $this->validator->validate($project, $contentHtml, []);
            $summary = [
                'inserted_links' => [],
                'existing_links' => [],
                'skipped_pages' => [],
                'validation' => $validation,
                'available_pages' => 0,
            ];
            $this->storeArticleSummary($article, $summary, $contentHtml);

            return $summary;
        }

        $dom = $this->loadFragment($contentHtml);
        $root = $this->rootElement($dom);
        $knownUrlMap = $this->knownUrlMap($sitePages);
        $usedUrls = $this->existingKnownUrls($dom, $knownUrlMap);
        $usedAnchors = $this->existingAnchors($dom);
        $selectedPages = $this->selectPages($sitePages);
        $inserted = [];
        $existing = [];
        $skipped = [];
        $position = 0;

        $this->clearPreviousSuggestions($article);
        $this->unlinkUnknownInternalAnchors($dom, $knownUrlMap, $this->knownHosts($sitePages));
        $this->unlinkRejectedInternalAnchors($dom, $knownUrlMap);
        $usedUrls = $this->existingKnownUrls($dom, $knownUrlMap);
        $usedAnchors = $this->existingAnchors($dom);

        foreach ($selectedPages as $sitePage) {
            if (count($inserted) + count($existing) >= self::MAX_LINKS) {
                break;
            }

            $pageKey = $this->canonicalPageKey($sitePage);
            if (isset($usedUrls[$pageKey])) {
                $anchor = $usedUrls[$pageKey];
                $existing[] = $this->linkPayload($sitePage, $anchor, 'existing');
                $this->persistSuggestion($article, $sitePage, $anchor, $position++, InternalLinkSuggestionStatus::INSERTED);
                continue;
            }

            $anchors = $this->candidateAnchors($sitePage, $usedAnchors);
            if ([] === $anchors) {
                $skipped[] = $this->linkPayload($sitePage, '', 'no_anchor');
                $this->persistSuggestion($article, $sitePage, $sitePage->getTitle(), null, InternalLinkSuggestionStatus::REJECTED);
                continue;
            }

            $placement = $this->insertAnchor($dom, $root, $sitePage, $anchors);
            if (null === $placement) {
                $skipped[] = $this->linkPayload($sitePage, implode(', ', $anchors), 'not_contextual');
                $this->persistSuggestion($article, $sitePage, $anchors[0], null, InternalLinkSuggestionStatus::REJECTED);
                continue;
            }

            $usedUrls[$pageKey] = $placement['anchor'];
            $usedAnchors[mb_strtolower($placement['anchor'])] = true;
            $inserted[] = $this->linkPayload($sitePage, $placement['anchor'], $placement['status']);
            $this->persistSuggestion($article, $sitePage, $placement['anchor'], $position++, InternalLinkSuggestionStatus::INSERTED);
        }

        $linkedHtml = $this->innerHtml($root);
        $validation = $this->validator->validate($project, $linkedHtml, $sitePages);
        $summary = [
            'inserted_links' => $inserted,
            'existing_links' => $existing,
            'skipped_pages' => $skipped,
            'validation' => $validation,
            'available_pages' => count($sitePages),
        ];

        $this->storeArticleSummary($article, $summary, $linkedHtml);
        if (!$validation['is_valid']) {
            $this->logger->warning('Internal linking validation completed with warnings.', [
                'article_id' => $article->getId(),
                'issues' => $validation['issues'],
            ]);
        }

        return $summary;
    }

    private function loadFragment(string $contentHtml): \DOMDocument
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="internal-linking-root">' . $contentHtml . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $dom;
    }

    private function rootElement(\DOMDocument $dom): \DOMElement
    {
        $root = $dom->getElementById('internal-linking-root');
        if (!$root instanceof \DOMElement) {
            throw new \RuntimeException('Unable to parse article HTML for internal linking.');
        }

        return $root;
    }

    /** @param list<SitePage> $sitePages */
    private function selectPages(array $sitePages): array
    {
        $groups = [];
        foreach ($sitePages as $sitePage) {
            $groups[$sitePage->getPageType()->value][] = $sitePage;
        }
        foreach ($groups as &$group) {
            usort($group, static fn(SitePage $left, SitePage $right): int => $right->getBusinessPriority() <=> $left->getBusinessPriority());
        }
        unset($group);

        $selected = [];
        $this->appendFromTypes($selected, $groups, [SitePageType::CONTACT, SitePageType::QUOTE], 1);
        $this->appendFromTypes($selected, $groups, [SitePageType::SERVICE, SitePageType::PRODUCT], 4);
        $this->appendFromTypes($selected, $groups, [SitePageType::CATEGORY], 1);
        $this->appendFromTypes($selected, $groups, [SitePageType::HOME], 1);
        $this->appendFromTypes($selected, $groups, [SitePageType::BLOG], 3);
        $this->appendFromTypes($selected, $groups, [SitePageType::OTHER], self::MAX_LINKS);

        if (count($selected) < self::MIN_LINKS) {
            $this->appendFromTypes($selected, $groups, SitePageType::cases(), self::MIN_LINKS);
        }

        return array_slice($selected, 0, self::MAX_LINKS);
    }

    /**
     * @param list<SitePage>               $selected
     * @param array<string, list<SitePage>> $groups
     * @param list<SitePageType>           $types
     */
    private function appendFromTypes(array &$selected, array $groups, array $types, int $limit): void
    {
        foreach ($types as $type) {
            foreach ($groups[$type->value] ?? [] as $sitePage) {
                if (count($selected) >= self::MAX_LINKS || $this->containsPage($selected, $sitePage)) {
                    continue;
                }
                if ($this->countSelectedTypes($selected, $types) >= $limit) {
                    return;
                }
                $selected[] = $sitePage;
            }
        }
    }

    /** @param list<SitePage> $selected */
    private function containsPage(array $selected, SitePage $sitePage): bool
    {
        foreach ($selected as $selectedPage) {
            if ($selectedPage->getId() === $sitePage->getId() || $selectedPage->getUrl() === $sitePage->getUrl()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<SitePage>     $selected
     * @param list<SitePageType> $types
     */
    private function countSelectedTypes(array $selected, array $types): int
    {
        $count = 0;
        foreach ($selected as $sitePage) {
            if (in_array($sitePage->getPageType(), $types, true)) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * @param array<string, bool> $usedAnchors
     *
     * @return list<string>
     */
    private function candidateAnchors(SitePage $sitePage, array $usedAnchors): array
    {
        $anchors = array_merge(
            $sitePage->getAnchorSuggestions(),
            [$sitePage->getTargetKeyword(), $sitePage->getTitle()],
            $this->fallbackAnchors($sitePage),
        );

        $candidates = [];
        foreach ($anchors as $anchor) {
            if (!is_string($anchor)) {
                continue;
            }
            $anchor = trim($anchor);
            if ('' === $anchor || isset($usedAnchors[mb_strtolower($anchor)]) || $this->isBannedAnchor($anchor)) {
                continue;
            }

            $candidates[] = mb_substr($anchor, 0, 120);
        }

        return array_values(array_unique($candidates));
    }

    /** @return list<string> */
    private function fallbackAnchors(SitePage $sitePage): array
    {
        $anchors = [];
        foreach ([$sitePage->getTargetKeyword(), $sitePage->getTitle()] as $candidate) {
            if (is_string($candidate) && '' !== trim($candidate)) {
                $anchors[] = trim($candidate);
            }
        }

        return $anchors;
    }

    /**
     * @param list<string> $anchors
     *
     * @return array{status: string, anchor: string}|null
     */
    private function insertAnchor(\DOMDocument $dom, \DOMElement $root, SitePage $sitePage, array $anchors): ?array
    {
        foreach ($this->paragraphs($root) as $paragraph) {
            if (!$this->canLinkParagraph($paragraph, $sitePage)) {
                continue;
            }

            foreach ($anchors as $anchor) {
                if ($this->insertInMatchingText($dom, $paragraph, $sitePage, $anchor)) {
                    return ['status' => 'matched_text', 'anchor' => $anchor];
                }
            }
        }

        return null;
    }

    private function insertInMatchingText(\DOMDocument $dom, \DOMElement $paragraph, SitePage $sitePage, string $anchor): bool
    {
        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query('.//text()[not(ancestor::a)]', $paragraph);
        if (!$nodes instanceof \DOMNodeList) {
            return false;
        }

        foreach ($nodes as $node) {
            if (!$node instanceof \DOMText) {
                continue;
            }

            $offset = mb_stripos($node->wholeText, $anchor);
            if (false === $offset) {
                continue;
            }
            if (!$this->isWholePhraseMatch($node->wholeText, $offset, $anchor)) {
                continue;
            }

            $before = mb_substr($node->wholeText, 0, $offset);
            $matched = mb_substr($node->wholeText, $offset, mb_strlen($anchor));
            $after = mb_substr($node->wholeText, $offset + mb_strlen($anchor));
            $fragment = $dom->createDocumentFragment();
            if ('' !== $before) {
                $fragment->appendChild($dom->createTextNode($before));
            }
            $link = $dom->createElement('a');
            $link->setAttribute('href', $sitePage->getUrl());
            $link->appendChild($dom->createTextNode($matched));
            $fragment->appendChild($link);
            if ('' !== $after) {
                $fragment->appendChild($dom->createTextNode($after));
            }

            $node->parentNode?->replaceChild($fragment, $node);

            return true;
        }

        return false;
    }

    /** @return list<\DOMElement> */
    private function paragraphs(\DOMElement $root): array
    {
        $paragraphs = [];
        foreach ($root->getElementsByTagName('p') as $paragraph) {
            if (!$this->hasAncestor($paragraph, ['table', 'thead', 'tbody', 'tr', 'td', 'th', 'h1', 'h2', 'h3'])) {
                $paragraphs[] = $paragraph;
            }
        }

        return $paragraphs;
    }

    private function canLinkParagraph(\DOMElement $paragraph, SitePage $sitePage): bool
    {
        if ($paragraph->getElementsByTagName('a')->length > 0) {
            return false;
        }

        $text = mb_strtolower($paragraph->textContent);
        if ($this->containsAny($text, self::REPETITIVE_PHRASES)) {
            return false;
        }

        if ($this->containsAny($text, self::PRICING_TERMS) && !in_array($sitePage->getPageType(), [SitePageType::CONTACT, SitePageType::QUOTE], true)) {
            return false;
        }

        if (in_array($sitePage->getPageType(), [SitePageType::CONTACT, SitePageType::QUOTE], true)) {
            return $this->containsAny($text, self::CONVERSION_TERMS);
        }

        return true;
    }

    /** @param list<string> $needles */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, mb_strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    private function unlinkUnknownInternalAnchors(\DOMDocument $dom, array $knownUrlMap, array $knownHosts): void
    {
        $anchors = [];
        foreach ($dom->getElementsByTagName('a') as $anchor) {
            $anchors[] = $anchor;
        }

        foreach ($anchors as $anchor) {
            $href = trim($anchor->getAttribute('href'));
            $key = $this->urlKey($href);
            if (null === $key || isset($knownUrlMap[$key]) || !$this->looksInternal($href, $knownHosts)) {
                continue;
            }

            $text = $dom->createTextNode($anchor->textContent);
            $anchor->parentNode?->replaceChild($text, $anchor);
        }
    }

    private function unlinkRejectedInternalAnchors(\DOMDocument $dom, array $knownUrlMap): void
    {
        $anchors = [];
        foreach ($dom->getElementsByTagName('a') as $anchor) {
            $anchors[] = $anchor;
        }

        foreach ($anchors as $anchor) {
            $key = $this->urlKey($anchor->getAttribute('href'));
            if (null === $key || !isset($knownUrlMap[$key])) {
                continue;
            }

            if (!$this->isBannedAnchor($anchor->textContent) && !$this->hasAncestor($anchor, ['h1', 'h2', 'h3', 'table', 'thead', 'tbody', 'tr', 'td', 'th'])) {
                continue;
            }

            $text = $dom->createTextNode($anchor->textContent);
            $anchor->parentNode?->replaceChild($text, $anchor);
        }
    }

    /** @return array<string, SitePage> */
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

    /** @return array<string, string> */
    private function existingKnownUrls(\DOMDocument $dom, array $knownUrlMap): array
    {
        $used = [];
        foreach ($dom->getElementsByTagName('a') as $anchor) {
            $key = $this->urlKey($anchor->getAttribute('href'));
            if (null !== $key && isset($knownUrlMap[$key]) && !$this->isBannedAnchor($anchor->textContent)) {
                $used[$this->canonicalPageKey($knownUrlMap[$key])] = trim($anchor->textContent);
            }
        }

        return $used;
    }

    /** @return array<string, bool> */
    private function existingAnchors(\DOMDocument $dom): array
    {
        $anchors = [];
        foreach ($dom->getElementsByTagName('a') as $anchor) {
            $text = trim($anchor->textContent);
            if ('' !== $text && !$this->isBannedAnchor($text)) {
                $anchors[mb_strtolower($text)] = true;
            }
        }

        return $anchors;
    }

    /** @return list<string> */
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

    private function canonicalPageKey(SitePage $sitePage): string
    {
        return $this->urlKeys($sitePage->getUrl())[0] ?? $sitePage->getUrl();
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

    private function innerHtml(\DOMElement $root): string
    {
        $html = '';
        foreach ($root->childNodes as $child) {
            $html .= $root->ownerDocument?->saveHTML($child) ?: '';
        }

        return trim($html);
    }

    private function stripRepetitiveLinkSentences(string $contentHtml): string
    {
        $dom = $this->loadFragment($contentHtml);
        $root = $this->rootElement($dom);
        $nodes = [];
        foreach (['p', 'li'] as $tagName) {
            foreach ($root->getElementsByTagName($tagName) as $node) {
                if ($this->containsAny(mb_strtolower($node->textContent), self::REPETITIVE_PHRASES)) {
                    $nodes[] = $node;
                }
            }
        }

        foreach ($nodes as $node) {
            $node->parentNode?->removeChild($node);
        }

        return $this->innerHtml($root);
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

    private function isWholePhraseMatch(string $text, int $offset, string $anchor): bool
    {
        $before = $offset > 0 ? mb_substr($text, $offset - 1, 1) : '';
        $afterOffset = $offset + mb_strlen($anchor);
        $after = $afterOffset < mb_strlen($text) ? mb_substr($text, $afterOffset, 1) : '';

        return !preg_match('/[\p{L}\p{N}]/u', $before) && !preg_match('/[\p{L}\p{N}]/u', $after);
    }

    private function clearPreviousSuggestions(Article $article): void
    {
        foreach ($this->suggestionRepository->findForArticle($article) as $suggestion) {
            $this->entityManager->remove($suggestion);
        }
    }

    private function persistSuggestion(
        Article $article,
        SitePage $sitePage,
        string $anchor,
        ?int $position,
        InternalLinkSuggestionStatus $status,
    ): void {
        $suggestion = new InternalLinkSuggestion();
        $suggestion
            ->setSourceArticle($article)
            ->setTargetPage($sitePage)
            ->setAnchor($anchor)
            ->setPosition($position)
            ->setStatus($status);

        $this->entityManager->persist($suggestion);
    }

    /**
     * @return array{url: string, title: string, type: string, anchor: string, status: string}
     */
    private function linkPayload(SitePage $sitePage, string $anchor, string $status): array
    {
        return [
            'url' => $sitePage->getUrl(),
            'title' => $sitePage->getTitle(),
            'type' => $sitePage->getPageType()->value,
            'anchor' => $anchor,
            'status' => $status,
        ];
    }

    /** @param array<string, mixed> $summary */
    private function storeArticleSummary(Article $article, array $summary, string $contentHtml): void
    {
        $metadata = $article->getGenerationMetadata() ?? [];
        $metadata['internal_linking'] = $summary + [
            'applied_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];

        $article
            ->setContentHtml($contentHtml)
            ->setInternalLinksJson(array_merge($summary['inserted_links'] ?? [], $summary['existing_links'] ?? []))
            ->setGenerationMetadata($metadata);
    }
}
