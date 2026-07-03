<?php

declare(strict_types=1);

namespace App\Service\InternalLinking;

use App\Entity\Article;
use App\Entity\AuditPage;
use App\Entity\Domain;
use App\Entity\Project;
use App\Entity\SitePage;
use App\Enum\SitePageType;
use App\Repository\ArticleRepository;
use App\Repository\AuditPageRepository;
use App\Repository\SitePageRepository;
use Doctrine\ORM\EntityManagerInterface;

final class SitePageDiscoveryService
{
    public function __construct(
        private readonly SitePageRepository $sitePageRepository,
        private readonly AuditPageRepository $auditPageRepository,
        private readonly ArticleRepository $articleRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /**
     * @return array{created: int, updated: int}
     */
    public function discover(Project $project): array
    {
        $created = 0;
        $updated = 0;
        /** @var array<string, SitePage> */
        $pendingByUrl = [];

        $primaryDomain = $project->getDomains()->first();
        if ($primaryDomain instanceof Domain) {
            $result = $this->upsertPage(
                $project,
                $this->normalizeUrl($primaryDomain->getRootDomain()),
                $project->getName(),
                SitePageType::HOME,
                $project->getName(),
                80,
                $pendingByUrl,
            );
            $created += $result['created'];
            $updated += $result['updated'];
        }

        foreach ($this->auditPageRepository->findIndexablePagesForLatestCompletedAudit($project) as $auditPage) {
            $url = $this->normalizeUrl($auditPage->getCanonicalUrl() ?? $auditPage->getNormalizedUrl() ?? $auditPage->getUrl());
            if (null === $url) {
                continue;
            }

            $title = $this->pageTitle($auditPage);
            $type = $this->classifyUrl($url, $title);
            $result = $this->upsertPage(
                $project,
                $url,
                $title,
                $type,
                $this->targetKeyword($auditPage, $title),
                $this->priorityForType($type),
                $pendingByUrl,
            );
            $created += $result['created'];
            $updated += $result['updated'];
        }

        foreach ($this->articleRepository->findPublishedWithKeywords($project) as $article) {
            $url = $this->articleUrl($article);
            if (null === $url) {
                continue;
            }

            $targetKeyword = $article->getPrimaryKeyword()?->getTerm() ?? $article->getTitle();
            $result = $this->upsertPage($project, $url, $article->getTitle(), SitePageType::BLOG, $targetKeyword, 45, $pendingByUrl);
            $created += $result['created'];
            $updated += $result['updated'];
        }

        $this->entityManager->flush();

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * @param array<string, SitePage> $pendingByUrl
     *
     * @return array{created: int, updated: int}
     */
    private function upsertPage(
        Project $project,
        ?string $url,
        string $title,
        SitePageType $type,
        ?string $targetKeyword,
        int $priority,
        array &$pendingByUrl = [],
    ): array {
        if (null === $url) {
            return ['created' => 0, 'updated' => 0];
        }

        $sitePage = $pendingByUrl[$url] ?? $this->sitePageRepository->findOneForProjectUrl($project, $url);
        $created = 0;
        $updated = 1;
        if (!$sitePage instanceof SitePage) {
            $sitePage = new SitePage();
            $sitePage->setProject($project)->setUrl($url);
            $this->entityManager->persist($sitePage);
            $pendingByUrl[$url] = $sitePage;
            $created = 1;
            $updated = 0;
        }

        $sitePage
            ->setTitle($title)
            ->setPageType($type)
            ->setTargetKeyword($targetKeyword)
            ->setBusinessPriority($priority)
            ->setAnchorSuggestions($this->anchorsFor($title, $targetKeyword, $type))
            ->setIsActive(true);

        return ['created' => $created, 'updated' => $updated];
    }

    private function pageTitle(AuditPage $auditPage): string
    {
        foreach ([$auditPage->getH1(), $auditPage->getTitle(), $auditPage->getUrl()] as $candidate) {
            if (is_string($candidate) && '' !== trim($candidate)) {
                return mb_substr(trim($candidate), 0, 500);
            }
        }

        return 'Page interne';
    }

    private function targetKeyword(AuditPage $auditPage, string $title): string
    {
        $h1 = $auditPage->getH1();

        return is_string($h1) && '' !== trim($h1) ? trim($h1) : $title;
    }

    private function articleUrl(Article $article): ?string
    {
        $slug = $article->getSlug();
        if (null === $slug || '' === trim($slug)) {
            return null;
        }

        return '/' . trim($slug, '/') . '/';
    }

    private function normalizeUrl(?string $url): ?string
    {
        if (null === $url || '' === trim($url)) {
            return null;
        }

        $url = trim($url);
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            $parts = parse_url($url);
            if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
                return null;
            }

            $path = $parts['path'] ?? '/';
            $normalized = strtolower((string) $parts['scheme']) . '://' . strtolower((string) $parts['host']) . $this->normalizePath($path);
            if (isset($parts['query']) && '' !== $parts['query']) {
                $normalized .= '?' . $parts['query'];
            }

            return $normalized;
        }

        return $this->normalizePath($url);
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        if ('/' !== $path && !str_contains(basename($path), '.')) {
            $path = rtrim($path, '/') . '/';
        }

        return $path;
    }

    private function classifyUrl(string $url, string $title): SitePageType
    {
        $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?: '/'));
        $haystack = $path . ' ' . strtolower($title);

        if ('/' === $path || '' === trim($path, '/')) {
            return SitePageType::HOME;
        }
        if (preg_match('/\b(contact|contacts|devis|quote|estimate|rendez-vous|rdv)\b/', $haystack)) {
            return str_contains($haystack, 'devis') || str_contains($haystack, 'quote') || str_contains($haystack, 'estimate')
                ? SitePageType::QUOTE
                : SitePageType::CONTACT;
        }
        if (preg_match('/\b(produit|product|shop|boutique|catalogue)\b/', $haystack)) {
            return SitePageType::PRODUCT;
        }
        if (preg_match('/\b(categorie|category|collection|gamme)\b/', $haystack)) {
            return SitePageType::CATEGORY;
        }
        if (preg_match('/\b(blog|article|news|actualite|guide|ressource)\b/', $haystack)) {
            return SitePageType::BLOG;
        }
        if (preg_match('/\b(service|prestation|solution|location|captation|streaming)\b/', $haystack)) {
            return SitePageType::SERVICE;
        }

        return SitePageType::OTHER;
    }

    private function priorityForType(SitePageType $type): int
    {
        return match ($type) {
            SitePageType::CONTACT, SitePageType::QUOTE => 95,
            SitePageType::SERVICE, SitePageType::PRODUCT => 85,
            SitePageType::CATEGORY => 70,
            SitePageType::HOME => 75,
            SitePageType::BLOG => 45,
            SitePageType::OTHER => 30,
        };
    }

    /** @return list<string> */
    private function anchorsFor(string $title, ?string $targetKeyword, SitePageType $type): array
    {
        $anchors = [$targetKeyword, $title];
        if (in_array($type, [SitePageType::CONTACT, SitePageType::QUOTE], true)) {
            $anchors[] = 'demander un devis';
        }

        $normalized = [];
        foreach ($anchors as $anchor) {
            if (is_string($anchor) && '' !== trim($anchor)) {
                $normalized[] = mb_substr(trim($anchor), 0, 120);
            }
        }

        return array_values(array_unique($normalized));
    }
}
