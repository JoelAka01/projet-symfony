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

final class ShopifyCmsClient implements CmsProviderClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CmsCredentialEncryption $credentialEncryption,
        private readonly CmsContentRenderer $contentRenderer,
        #[Autowire('%env(string:SHOPIFY_API_VERSION)%')]
        private readonly string $apiVersion,
        #[Autowire('%env(int:CMS_HTTP_TIMEOUT_SECONDS)%')]
        private readonly int $timeoutSeconds,
    ) {}

    public function provider(): CmsProvider
    {
        return CmsProvider::SHOPIFY;
    }

    public function testConnection(CmsConnection $connection): CmsConnectionTestResult
    {
        $data = $this->graphql(
            $connection,
            <<<'GRAPHQL'
query CmsConnectionTest {
  shop {
    name
    myshopifyDomain
  }
  blogs(first: 50) {
    nodes {
      id
      title
      handle
    }
  }
}
GRAPHQL,
            [],
        );

        $blogConnection = is_array($data['blogs'] ?? null) ? $data['blogs'] : [];
        $nodes = is_array($blogConnection['nodes'] ?? null) ? $blogConnection['nodes'] : [];
        $blogs = [];
        foreach ($nodes as $node) {
            if (!is_array($node) || !is_scalar($node['id'] ?? null)) {
                continue;
            }

            $blogs[] = [
                'id' => (string) $node['id'],
                'title' => is_scalar($node['title'] ?? null) ? (string) $node['title'] : 'Untitled blog',
                'handle' => is_scalar($node['handle'] ?? null) ? (string) $node['handle'] : null,
            ];
        }

        if ([] === $blogs) {
            throw new CmsIntegrationException('Shopify authenticated successfully, but no accessible blog was found. Add a blog and grant the custom app read_content and write_content access.');
        }

        $configuredBlogId = $this->optionalSetting($connection, 'blog_id');
        $blog = null;
        foreach ($blogs as $candidate) {
            if (null === $configuredBlogId || $candidate['id'] === $configuredBlogId) {
                $blog = $candidate;
                break;
            }
        }

        if (null === $blog) {
            throw new CmsIntegrationException('The selected Shopify blog is no longer accessible. Edit the connection and select another blog.');
        }

        $shop = is_array($data['shop'] ?? null) ? $data['shop'] : [];
        $shopName = is_scalar($shop['name'] ?? null) ? (string) $shop['name'] : 'Shopify store';
        $shopDomain = is_scalar($shop['myshopifyDomain'] ?? null) ? (string) $shop['myshopifyDomain'] : null;

        return new CmsConnectionTestResult(
            sprintf('Connected to %s. Articles will be sent to "%s".', $shopName, $blog['title']),
            [
                'shop' => $shopName,
                'shop_domain' => $shopDomain,
                'blog_id' => $blog['id'],
                'blog_title' => $blog['title'],
                'available_blogs' => $blogs,
            ],
        );
    }

    public function publishArticle(
        CmsConnection $connection,
        Article $article,
        ?CmsPublication $existingPublication,
        bool $publish,
    ): CmsPublishResult {
        $input = $this->articleInput($connection, $article, $publish);
        $externalId = $existingPublication?->getExternalPostId();

        if (null === $externalId) {
            $data = $this->graphql(
                $connection,
                <<<'GRAPHQL'
mutation CreateArticle($article: ArticleCreateInput!) {
  articleCreate(article: $article) {
    article {
      id
      handle
      blog {
        handle
      }
    }
    userErrors {
      field
      message
    }
  }
}
GRAPHQL,
                ['article' => $input],
            );
            $payload = is_array($data['articleCreate'] ?? null) ? $data['articleCreate'] : [];
        } else {
            unset($input['blogId'], $input['author']);
            $data = $this->graphql(
                $connection,
                <<<'GRAPHQL'
mutation UpdateArticle($id: ID!, $article: ArticleUpdateInput!) {
  articleUpdate(id: $id, article: $article) {
    article {
      id
      handle
      blog {
        handle
      }
    }
    userErrors {
      field
      message
    }
  }
}
GRAPHQL,
                [
                    'id' => $externalId,
                    'article' => $input,
                ],
            );
            $payload = is_array($data['articleUpdate'] ?? null) ? $data['articleUpdate'] : [];
        }

        $this->assertNoUserErrors($payload);
        $remoteArticle = is_array($payload['article'] ?? null) ? $payload['article'] : [];
        if (!is_scalar($remoteArticle['id'] ?? null)) {
            throw new CmsIntegrationException('Shopify did not return an article ID.');
        }

        $blog = is_array($remoteArticle['blog'] ?? null) ? $remoteArticle['blog'] : [];
        $url = $this->articleUrl(
            $connection,
            is_scalar($blog['handle'] ?? null) ? (string) $blog['handle'] : null,
            is_scalar($remoteArticle['handle'] ?? null) ? (string) $remoteArticle['handle'] : null,
        );

        $warnings = [];
        if (null !== $article->getSeoDescription()) {
            $warnings[] = 'Shopify Article uses the SEO description as the article summary because ArticleCreateInput has no separate SEO description field.';
        }

        if ($article->getImages()->count() > 1) {
            $warnings[] = 'Shopify uses the first image as the featured image; remaining images are inserted into the article body.';
        }

        return new CmsPublishResult(
            (string) $remoteArticle['id'],
            $url,
            $warnings,
            [
                'api_version' => $this->apiVersion,
                'published' => $publish,
            ],
        );
    }

    /** @return array<string, mixed> */
    private function articleInput(CmsConnection $connection, Article $article, bool $publish): array
    {
        $input = [
            'blogId' => $this->setting($connection, 'blog_id'),
            'title' => $article->getSeoTitle() ?: $article->getTitle(),
            'body' => $this->contentRenderer->render($article),
            'summary' => $article->getExcerpt() ?: $article->getSeoDescription() ?: '',
            'isPublished' => $publish,
            'author' => [
                'name' => $this->setting($connection, 'author_name'),
            ],
            'tags' => $this->tags($article),
        ];

        if (null !== $article->getSlug() && '' !== trim($article->getSlug())) {
            $input['handle'] = $article->getSlug();
        }

        $featuredImage = $article->getImages()->first();
        if (false !== $featuredImage) {
            $input['image'] = [
                'url' => $featuredImage->getStorageUrl(),
                'altText' => (string) $featuredImage->getAltText(),
            ];
        }

        return $input;
    }

    /** @return list<string> */
    private function tags(Article $article): array
    {
        $tags = [];
        if (null !== $article->getPrimaryKeyword()) {
            $tags[] = $article->getPrimaryKeyword()->getTerm();
        }

        foreach ($article->getTargetKeywords() as $keyword) {
            $tags[] = $keyword->getTerm();
        }

        return array_values(array_unique(array_filter(array_map('trim', $tags))));
    }

    /** @param array<string, mixed> $variables
     * @return array<string, mixed>
     */
    private function graphql(CmsConnection $connection, string $query, array $variables): array
    {
        $version = preg_match('/^\d{4}-\d{2}$/', $this->apiVersion) ? $this->apiVersion : '2026-04';
        $response = $this->httpClient->request(
            'POST',
            rtrim($connection->getBaseUrl(), '/') . '/admin/api/' . $version . '/graphql.json',
            [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'X-Shopify-Access-Token' => $this->credentialEncryption->decrypt($connection->getEncryptedAccessToken()),
                ],
                'json' => [
                    'query' => $query,
                    'variables' => $variables,
                ],
                'timeout' => max(5, $this->timeoutSeconds),
                'max_duration' => max(10, $this->timeoutSeconds * 2),
            ],
        );

        $statusCode = $response->getStatusCode();
        $body = $response->getContent(false);
        if ($statusCode >= 400) {
            throw new CmsIntegrationException(sprintf('Shopify returned HTTP %d: %s', $statusCode, substr(strip_tags($body), 0, 1000)));
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new CmsIntegrationException('Shopify returned invalid JSON.', previous: $exception);
        }

        if (!is_array($decoded)) {
            throw new CmsIntegrationException('Shopify returned an unexpected response.');
        }

        if (is_array($decoded['errors'] ?? null) && [] !== $decoded['errors']) {
            $messages = array_map(
                static fn(mixed $error): string => is_array($error) && is_scalar($error['message'] ?? null)
                    ? (string) $error['message']
                    : 'Unknown GraphQL error',
                $decoded['errors'],
            );
            throw new CmsIntegrationException('Shopify GraphQL error: ' . implode('; ', $messages));
        }

        $data = $decoded['data'] ?? null;
        if (!is_array($data)) {
            throw new CmsIntegrationException('Shopify response did not contain GraphQL data.');
        }

        return $data;
    }

    /** @param array<string, mixed> $payload */
    private function assertNoUserErrors(array $payload): void
    {
        $errors = is_array($payload['userErrors'] ?? null) ? $payload['userErrors'] : [];
        if ([] === $errors) {
            return;
        }

        $messages = array_map(
            static fn(mixed $error): string => is_array($error) && is_scalar($error['message'] ?? null)
                ? (string) $error['message']
                : 'Unknown Shopify validation error',
            $errors,
        );

        throw new CmsIntegrationException('Shopify rejected the article: ' . implode('; ', $messages));
    }

    private function setting(CmsConnection $connection, string $name): string
    {
        $value = $this->optionalSetting($connection, $name);
        if (null === $value) {
            throw new CmsIntegrationException(sprintf('Shopify setting "%s" is not configured.', $name));
        }

        return $value;
    }

    private function optionalSetting(CmsConnection $connection, string $name): ?string
    {
        $settings = $connection->getSettings() ?? [];
        $value = is_scalar($settings[$name] ?? null) ? trim((string) $settings[$name]) : '';

        return '' === $value ? null : $value;
    }

    private function articleUrl(CmsConnection $connection, ?string $blogHandle, ?string $articleHandle): ?string
    {
        if (null === $blogHandle || null === $articleHandle || '' === $blogHandle || '' === $articleHandle) {
            return null;
        }

        return sprintf(
            '%s/blogs/%s/%s',
            rtrim($connection->getBaseUrl(), '/'),
            rawurlencode($blogHandle),
            rawurlencode($articleHandle),
        );
    }
}
