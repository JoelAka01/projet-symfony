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

            $anchor = $this->chooseAnchor($sitePage, $usedAnchors);
            if (null === $anchor) {
                $skipped[] = $this->linkPayload($sitePage, '', 'no_anchor');
                $this->persistSuggestion($article, $sitePage, $sitePage->getTitle(), null, InternalLinkSuggestionStatus::REJECTED);
                continue;
            }

            $placement = $this->insertAnchor($dom, $root, $sitePage, $anchor);
            if (null === $placement) {
                $skipped[] = $this->linkPayload($sitePage, $anchor, 'not_inserted');
                $this->persistSuggestion($article, $sitePage, $anchor, null, InternalLinkSuggestionStatus::FAILED);
                continue;
            }

            $usedUrls[$pageKey] = $anchor;
            $usedAnchors[mb_strtolower($anchor)] = true;
            $inserted[] = $this->linkPayload($sitePage, $anchor, $placement);
            $this->persistSuggestion($article, $sitePage, $anchor, $position++, InternalLinkSuggestionStatus::INSERTED);
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

    /** @param array<string, bool> $usedAnchors */
    private function chooseAnchor(SitePage $sitePage, array $usedAnchors): ?string
    {
        $anchors = array_merge(
            $sitePage->getAnchorSuggestions(),
            [$sitePage->getTargetKeyword(), $sitePage->getTitle()],
            $this->fallbackAnchors($sitePage),
        );

        foreach ($anchors as $anchor) {
            if (!is_string($anchor)) {
                continue;
            }
            $anchor = trim($anchor);
            if ('' === $anchor || isset($usedAnchors[mb_strtolower($anchor)])) {
                continue;
            }

            return mb_substr($anchor, 0, 120);
        }

        return null;
    }

    /** @return list<string> */
    private function fallbackAnchors(SitePage $sitePage): array
    {
        return match ($sitePage->getPageType()) {
            SitePageType::HOME => ['site officiel', 'page d\'accueil'],
            SitePageType::CONTACT => ['nous contacter', 'prendre contact'],
            SitePageType::QUOTE => ['demander un devis', 'obtenir un devis'],
            SitePageType::SERVICE => ['ce service', 'notre service dedie'],
            SitePageType::PRODUCT => ['ce produit', 'cette offre'],
            SitePageType::CATEGORY => ['cette categorie', 'nos solutions associees'],
            SitePageType::BLOG => ['ce guide complementaire', 'cet article complementaire'],
            SitePageType::OTHER => ['cette page utile', 'en savoir plus'],
        };
    }

    private function insertAnchor(\DOMDocument $dom, \DOMElement $root, SitePage $sitePage, string $anchor): ?string
    {
        foreach ($this->paragraphs($root) as $paragraph) {
            if ($this->insertInMatchingText($dom, $paragraph, $sitePage, $anchor)) {
                return 'matched_text';
            }
        }

        $paragraph = $this->bestFallbackParagraph($root);
        if (!$paragraph instanceof \DOMElement) {
            return null;
        }

        if ('' !== trim($paragraph->textContent)) {
            $paragraph->appendChild($dom->createTextNode(' '));
        }
        $paragraph->appendChild($dom->createTextNode($this->introFor($sitePage) . ' '));
        $link = $dom->createElement('a');
        $link->setAttribute('href', $sitePage->getUrl());
        $link->appendChild($dom->createTextNode($anchor));
        $paragraph->appendChild($link);
        $paragraph->appendChild($dom->createTextNode('.'));

        return 'fallback_sentence';
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

    private function bestFallbackParagraph(\DOMElement $root): ?\DOMElement
    {
        $paragraphs = $this->paragraphs($root);
        if ([] === $paragraphs) {
            return null;
        }

        return $paragraphs[(int) floor((count($paragraphs) - 1) / 2)];
    }

    private function introFor(SitePage $sitePage): string
    {
        return match ($sitePage->getPageType()) {
            SitePageType::CONTACT, SitePageType::QUOTE => 'Pour avancer sur votre projet, vous pouvez aussi',
            SitePageType::HOME => 'Pour replacer le sujet dans l\'offre globale, consultez',
            SitePageType::BLOG => 'Un contenu complementaire utile est disponible ici :',
            default => 'Pour approfondir ce point, consultez',
        };
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
            if (null !== $key && isset($knownUrlMap[$key])) {
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
            if ('' !== $text) {
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
