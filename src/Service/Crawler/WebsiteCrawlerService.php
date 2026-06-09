<?php

declare(strict_types=1);

namespace App\Service\Crawler;

use App\Entity\Audit;
use App\Entity\AuditIssue;
use App\Entity\AuditPage;
use App\Enum\AuditStatus;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class WebsiteCrawlerService
{
    private const USER_AGENT = 'SEO-GEO-AI-SchoolCrawler/1.0';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly CrawlerUrlNormalizer $urlNormalizer,
        private readonly HtmlSeoExtractor $htmlSeoExtractor,
        private readonly SeoIssueDetector $issueDetector,
        private readonly SeoScoreCalculator $scoreCalculator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getConfiguredMaxPages(): int
    {
        return $this->envInt('CRAWLER_MAX_PAGES', 30, 1, 500);
    }

    public function getConfiguredMaxDepth(): int
    {
        return $this->envInt('CRAWLER_MAX_DEPTH', 2, 0, 20);
    }

    public function crawl(Audit $audit): void
    {
        $maxPages = $this->boundInt($audit->getMaxPages() ?? $this->getConfiguredMaxPages(), 1, 500);
        $maxDepth = $this->boundInt($audit->getMaxDepth() ?? $this->getConfiguredMaxDepth(), 0, 20);
        $timeoutSeconds = $this->envInt('CRAWLER_TIMEOUT_SECONDS', 10, 1, 60);
        $delayMs = $this->envInt('CRAWLER_DELAY_MS', 250, 0, 5000);

        $audit
            ->setStatus(AuditStatus::RUNNING)
            ->setCrawlStartedAt($audit->getCrawlStartedAt() ?? new \DateTimeImmutable())
            ->setMaxPages($maxPages)
            ->setMaxDepth($maxDepth)
            ->setPagesCrawled(0)
            ->setPagesFailed(0)
            ->setErrorMessage(null);

        $domain = $audit->getDomain();
        $project = $audit->getProject();
        if (null === $domain || null === $project) {
            $this->failAudit($audit, 'Audit must be attached to a project and domain before crawling.');

            return;
        }

        $startUrl = $this->urlNormalizer->normalizeStartUrl($domain->getRootDomain());
        $startHostname = null === $startUrl ? null : $this->urlNormalizer->getHostname($startUrl);
        if (null === $startUrl || null === $startHostname) {
            $this->failAudit($audit, sprintf('Domain "%s" is not a valid public HTTP(S) crawl target.', $domain->getRootDomain()));

            return;
        }

        $queue = [[$startUrl, 0]];
        $queued = [$startUrl => true];
        $visited = [];
        $seenTitles = [];
        $seenMetaDescriptions = [];
        $allIssues = [];
        $pagesFailed = 0;

        while ([] !== $queue && count($visited) < $maxPages) {
            [$url, $depth] = array_shift($queue);
            unset($queued[$url]);

            if (isset($visited[$url])) {
                continue;
            }

            $visited[$url] = true;
            $page = $this->createPage($audit, $url);
            $this->entityManager->persist($page);

            $pageFailed = false;
            $startedAt = microtime(true);

            try {
                $response = $this->httpClient->request('GET', $url, [
                    'headers' => [
                        'User-Agent' => self::USER_AGENT,
                        'Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.1',
                    ],
                    'max_redirects' => 0,
                    'timeout' => $timeoutSeconds,
                ]);

                $statusCode = $response->getStatusCode();
                $headers = $response->getHeaders(false);
                $contentType = $this->firstHeader($headers, 'content-type');
                $loadTimeMs = $this->elapsedMs($startedAt);

                $page
                    ->setStatusCode($statusCode)
                    ->setContentType($contentType)
                    ->setLoadTimeMs($loadTimeMs);

                if ($statusCode >= 400) {
                    $pageFailed = true;
                }

                $this->enqueueRedirectIfAllowed($headers, $statusCode, $url, $depth, $maxDepth, $maxPages, $startHostname, $queue, $queued, $visited);

                $extractionResult = null;
                if ($this->canReadHtmlBody($contentType, $statusCode)) {
                    $body = $response->getContent(false);
                    if ($this->isHtmlBody($contentType, $body)) {
                        $page->setContentHash(hash('sha256', $body));
                        $extractionResult = $this->htmlSeoExtractor->extract($body, $url, $startHostname);
                        $this->fillPageFromExtraction($page, $extractionResult);

                        if ($depth < $maxDepth) {
                            $this->enqueueLinks($extractionResult->internalLinks, $maxPages, $queue, $queued, $visited, $depth + 1);
                        }
                    }
                }

                $issues = $this->issueDetector->detect(
                    $extractionResult,
                    $statusCode,
                    $loadTimeMs,
                    $audit,
                    $page,
                    $startHostname,
                    $seenTitles,
                    $seenMetaDescriptions,
                );
                $this->persistIssues($issues);
                array_push($allIssues, ...$issues);
            } catch (\Throwable $exception) {
                $pageFailed = true;
                $message = $this->limit($exception->getMessage(), 1000) ?? 'Unknown fetch error.';
                $page
                    ->setLoadTimeMs($this->elapsedMs($startedAt))
                    ->setErrorMessage($message);

                $issue = $this->issueDetector->createFetchErrorIssue($audit, $page, $message);
                $this->entityManager->persist($issue);
                $allIssues[] = $issue;

                $this->logger->warning('Crawler fetch failed.', [
                    'url' => $url,
                    'exception' => $exception,
                ]);
            }

            if ($pageFailed) {
                ++$pagesFailed;
            }

            $audit
                ->setPagesCrawled(count($visited))
                ->setPagesFailed($pagesFailed);

            $this->entityManager->flush();

            if ($delayMs > 0 && [] !== $queue && count($visited) < $maxPages) {
                usleep($delayMs * 1000);
            }
        }

        $score = $this->scoreCalculator->calculate($allIssues);
        $metadata = $audit->getMetadata() ?? [];
        $metadata['crawler'] = [
            'start_url' => $startUrl,
            'hostname' => $startHostname,
            'timeout_seconds' => $timeoutSeconds,
            'delay_ms' => $delayMs,
        ];

        $audit
            ->setSeoScore($score)
            ->setPagesCrawled(count($visited))
            ->setPagesFailed($pagesFailed)
            ->setMetadata($metadata)
            ->setStatus(AuditStatus::COMPLETED)
            ->setCrawlFinishedAt(new \DateTimeImmutable());

        $project->setSeoScore($score);

        $this->entityManager->flush();
    }

    private function createPage(Audit $audit, string $url): AuditPage
    {
        $page = new AuditPage();
        $page
            ->setUrl($url)
            ->setNormalizedUrl($url);

        $audit->addPage($page);

        return $page;
    }

    private function fillPageFromExtraction(AuditPage $page, HtmlSeoExtractionResult $result): void
    {
        $page
            ->setTitle($this->limit($result->title, 500))
            ->setMetaDescription($result->metaDescription)
            ->setH1([] === $result->h1Headings ? null : implode("\n", $result->h1Headings))
            ->setCanonicalUrl($result->canonicalUrl)
            ->setRobotsMeta($result->robotsMeta)
            ->setWordCount($result->wordCount)
            ->setInternalLinksCount(count($result->internalLinks))
            ->setExternalLinksCount(count($result->externalLinks))
            ->setImagesWithoutAltCount($result->imagesWithoutAltCount)
            ->setStructuredDataPresent($result->hasStructuredData)
            ->setIsIndexable(null === $result->robotsMeta || !str_contains(strtolower($result->robotsMeta), 'noindex'));
    }

    /**
     * @param array<string, list<string>> $headers
     * @param list<array{0: string, 1: int}> $queue
     * @param array<string, true> $queued
     * @param array<string, true> $visited
     */
    private function enqueueRedirectIfAllowed(
        array $headers,
        int $statusCode,
        string $url,
        int $depth,
        int $maxDepth,
        int $maxPages,
        string $startHostname,
        array &$queue,
        array &$queued,
        array $visited,
    ): void {
        if ($statusCode < 300 || $statusCode >= 400) {
            return;
        }

        if ($depth > $maxDepth) {
            return;
        }

        $location = $this->firstHeader($headers, 'location');
        if (null === $location) {
            return;
        }

        $redirectUrl = $this->urlNormalizer->normalizeForCrawl($location, $url, $startHostname);
        if (null === $redirectUrl) {
            return;
        }

        $this->enqueueLinks([$redirectUrl], $maxPages, $queue, $queued, $visited, $depth);
    }

    /**
     * @param list<string> $links
     * @param list<array{0: string, 1: int}> $queue
     * @param array<string, true> $queued
     * @param array<string, true> $visited
     */
    private function enqueueLinks(array $links, int $maxPages, array &$queue, array &$queued, array $visited, int $depth): void
    {
        foreach ($links as $link) {
            if (isset($visited[$link]) || isset($queued[$link])) {
                continue;
            }

            if ((count($visited) + count($queued)) >= $maxPages) {
                return;
            }

            $queue[] = [$link, $depth];
            $queued[$link] = true;
        }
    }

    /** @param list<AuditIssue> $issues */
    private function persistIssues(array $issues): void
    {
        foreach ($issues as $issue) {
            $this->entityManager->persist($issue);
        }
    }

    /** @param array<string, list<string>> $headers */
    private function firstHeader(array $headers, string $name): ?string
    {
        $name = strtolower($name);
        foreach ($headers as $headerName => $values) {
            if (strtolower((string) $headerName) === $name && [] !== $values) {
                return $values[0];
            }
        }

        return null;
    }

    private function canReadHtmlBody(?string $contentType, int $statusCode): bool
    {
        if ($statusCode >= 300 && $statusCode < 400) {
            return false;
        }

        return null === $contentType
            || str_contains(strtolower($contentType), 'text/html')
            || str_contains(strtolower($contentType), 'application/xhtml+xml');
    }

    private function isHtmlBody(?string $contentType, string $body): bool
    {
        if (null !== $contentType && (
            str_contains(strtolower($contentType), 'text/html')
            || str_contains(strtolower($contentType), 'application/xhtml+xml')
        )) {
            return true;
        }

        $prefix = strtolower(substr(ltrim($body), 0, 120));

        return str_contains($prefix, '<!doctype html') || str_contains($prefix, '<html');
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function failAudit(Audit $audit, string $message): void
    {
        $audit
            ->setStatus(AuditStatus::FAILED)
            ->setErrorMessage($message)
            ->setCrawlFinishedAt(new \DateTimeImmutable());

        $this->entityManager->persist($audit);
        $this->entityManager->flush();
    }

    private function envInt(string $name, int $default, int $min, int $max): int
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? false;
        if (false === $value) {
            $value = getenv($name);
        }

        if (!is_scalar($value) || '' === (string) $value) {
            return $default;
        }

        $integerValue = filter_var($value, FILTER_VALIDATE_INT);
        if (false === $integerValue) {
            return $default;
        }

        return $this->boundInt($integerValue, $min, $max);
    }

    private function boundInt(int $value, int $min, int $max): int
    {
        return max($min, min($max, $value));
    }

    private function limit(?string $value, int $maxLength): ?string
    {
        if (null === $value) {
            return null;
        }

        return strlen($value) > $maxLength ? substr($value, 0, $maxLength) : $value;
    }
}
