<?php

declare(strict_types=1);

namespace App\Service\Cms;

use App\Dto\Cms\CmsConnectionTestResult;
use App\Dto\Cms\CmsPublishResult;
use App\Entity\Article;
use App\Entity\CmsConnection;
use App\Entity\CmsPublication;
use App\Enum\CmsProvider;
use App\Exception\CmsIntegrationException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class WordPressCmsClient implements CmsProviderClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CmsCredentialEncryption $credentialEncryption,
        private readonly ExternalImageFetcher $imageFetcher,
        private readonly CmsContentRenderer $contentRenderer,
        #[Autowire('%env(int:CMS_HTTP_TIMEOUT_SECONDS)%')]
        private readonly int $timeoutSeconds,
    ) {}

    public function provider(): CmsProvider
    {
        return CmsProvider::WORDPRESS;
    }

    public function testConnection(CmsConnection $connection): CmsConnectionTestResult
    {
        $data = $this->json($this->request($connection, 'GET', '/wp-json/wp/v2/users/me?context=edit'));
        $name = is_scalar($data['name'] ?? null) ? (string) $data['name'] : 'WordPress user';

        return new CmsConnectionTestResult(
            sprintf('Connected to WordPress as %s.', $name),
            [
                'user_id' => $data['id'] ?? null,
                'user_name' => $name,
            ],
        );
    }

    public function publishArticle(
        CmsConnection $connection,
        Article $article,
        ?CmsPublication $existingPublication,
        bool $publish,
    ): CmsPublishResult {
        $uploadedUrls = [];
        $featuredMediaId = null;

        foreach ($article->getImages() as $image) {
            $uploaded = $this->uploadMedia(
                $connection,
                $image->getStorageUrl(),
                (string) $image->getAltText(),
            );
            $uploadedUrls[] = $uploaded['url'];
            $featuredMediaId ??= $uploaded['id'];
        }

        $payload = [
            'title' => $article->getSeoTitle() ?: $article->getTitle(),
            'content' => $this->contentRenderer->render($article, $uploadedUrls),
            'status' => $publish ? 'publish' : 'draft',
        ];

        if (null !== $article->getSlug() && '' !== trim($article->getSlug())) {
            $payload['slug'] = $article->getSlug();
        }

        $excerpt = $article->getExcerpt() ?: $article->getSeoDescription();
        if (null !== $excerpt && '' !== trim($excerpt)) {
            $payload['excerpt'] = $excerpt;
        }

        if (null !== $featuredMediaId) {
            $payload['featured_media'] = $featuredMediaId;
        }

        $externalId = $existingPublication?->getExternalPostId();
        $path = null !== $externalId
            ? '/wp-json/wp/v2/posts/' . rawurlencode($externalId)
            : '/wp-json/wp/v2/posts';
        $data = $this->json($this->request($connection, 'POST', $path, ['json' => $payload]));

        if (!is_scalar($data['id'] ?? null)) {
            throw new CmsIntegrationException('WordPress did not return a post ID.');
        }

        $warnings = [];
        if (null !== $article->getSeoDescription()) {
            $warnings[] = 'WordPress core received the SEO description as the post excerpt. A plugin-specific meta description requires that plugin to expose a writable REST field.';
        }

        return new CmsPublishResult(
            (string) $data['id'],
            is_scalar($data['link'] ?? null) ? (string) $data['link'] : null,
            $warnings,
            [
                'remote_status' => $data['status'] ?? null,
                'uploaded_images' => count($uploadedUrls),
            ],
        );
    }

    /** @return array{id: int, url: string} */
    private function uploadMedia(CmsConnection $connection, string $url, string $altText): array
    {
        $image = $this->imageFetcher->fetch($url);
        $query = '' === trim($altText) ? '' : '?' . http_build_query(['alt_text' => $altText]);
        $data = $this->json($this->request(
            $connection,
            'POST',
            '/wp-json/wp/v2/media' . $query,
            [
                'headers' => [
                    'Content-Type' => $image->contentType,
                    'Content-Disposition' => sprintf('attachment; filename="%s"', $image->filename),
                ],
                'body' => $image->contents,
            ],
        ));

        if (!is_numeric($data['id'] ?? null) || !is_scalar($data['source_url'] ?? null)) {
            throw new CmsIntegrationException('WordPress media upload did not return an ID and source URL.');
        }

        return [
            'id' => (int) $data['id'],
            'url' => (string) $data['source_url'],
        ];
    }

    /** @param array<string, mixed> $options */
    private function request(
        CmsConnection $connection,
        string $method,
        string $path,
        array $options = [],
    ): ResponseInterface {
        $settings = $connection->getSettings() ?? [];
        $username = is_scalar($settings['username'] ?? null) ? trim((string) $settings['username']) : '';
        if ('' === $username) {
            throw new CmsIntegrationException('The WordPress username is not configured.');
        }

        $options['auth_basic'] = [$username, $this->credentialEncryption->decrypt($connection->getEncryptedAccessToken())];
        $options['timeout'] = max(5, $this->timeoutSeconds);
        $options['max_duration'] = max(10, $this->timeoutSeconds * 2);
        $options['headers'] = array_merge([
            'Accept' => 'application/json',
        ], is_array($options['headers'] ?? null) ? $options['headers'] : []);

        $response = $this->httpClient->request(
            $method,
            rtrim($connection->getBaseUrl(), '/') . $path,
            $options,
        );

        $statusCode = $response->getStatusCode();
        if ($statusCode >= 400) {
            $body = $response->getContent(false);
            throw new CmsIntegrationException(sprintf('WordPress returned HTTP %d: %s', $statusCode, $this->errorMessage($body)));
        }

        return $response;
    }

    /** @return array<string, mixed> */
    private function json(ResponseInterface $response): array
    {
        try {
            $data = json_decode($response->getContent(false), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new CmsIntegrationException('WordPress returned invalid JSON.', previous: $exception);
        }

        if (!is_array($data)) {
            throw new CmsIntegrationException('WordPress returned an unexpected response.');
        }

        return $data;
    }

    private function errorMessage(string $body): string
    {
        $data = json_decode($body, true);
        if (is_array($data) && is_scalar($data['message'] ?? null)) {
            return substr(strip_tags((string) $data['message']), 0, 800);
        }

        return substr(strip_tags($body), 0, 800);
    }
}
