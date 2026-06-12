<?php

declare(strict_types=1);

namespace App\Service\Cms;

use App\Dto\Cms\FetchedImage;
use App\Exception\CmsIntegrationException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ExternalImageFetcher
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(int:CMS_HTTP_TIMEOUT_SECONDS)%')]
        private readonly int $timeoutSeconds,
        #[Autowire('%env(int:CMS_MAX_IMAGE_BYTES)%')]
        private readonly int $maxImageBytes,
    ) {}

    public function fetch(string $url): FetchedImage
    {
        $this->assertPublicHttpUrl($url);

        $response = $this->httpClient->request('GET', $url, [
            'timeout' => max(5, $this->timeoutSeconds),
            'max_duration' => max(10, $this->timeoutSeconds * 2),
            'max_redirects' => 3,
            'headers' => [
                'Accept' => 'image/*',
            ],
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode >= 400) {
            throw new CmsIntegrationException(sprintf('Image download returned HTTP %d.', $statusCode));
        }

        $headers = $response->getHeaders(false);
        $contentType = strtolower(trim(explode(';', $headers['content-type'][0] ?? '')[0]));
        if (!str_starts_with($contentType, 'image/')) {
            throw new CmsIntegrationException('The configured article image URL did not return an image.');
        }

        $contents = $response->getContent(false);
        if (strlen($contents) > max(1024, $this->maxImageBytes)) {
            throw new CmsIntegrationException(sprintf('The article image exceeds the configured %d byte limit.', $this->maxImageBytes));
        }

        return new FetchedImage($contents, $contentType, $this->filename($url, $contentType));
    }

    private function assertPublicHttpUrl(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (!in_array($scheme, ['http', 'https'], true) || '' === $host) {
            throw new CmsIntegrationException('Article image URLs must use http or https.');
        }

        if ('localhost' === $host || str_ends_with($host, '.local')) {
            throw new CmsIntegrationException('Private or local article image URLs are not allowed.');
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if ([] === $addresses) {
            throw new CmsIntegrationException('The article image hostname could not be resolved.');
        }

        foreach ($addresses as $address) {
            if (false === filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new CmsIntegrationException('Private or reserved article image addresses are not allowed.');
            }
        }
    }

    private function filename(string $url, string $contentType): string
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        $filename = basename($path);
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '-', $filename) ?: '';

        if ('' !== $filename && str_contains($filename, '.')) {
            return substr($filename, 0, 180);
        }

        $extension = match ($contentType) {
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            default => 'jpg',
        };

        return 'article-image.' . $extension;
    }
}
