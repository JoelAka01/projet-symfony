<?php

declare(strict_types=1);

namespace App\Service\Cms;

use App\Entity\CmsConnection;
use App\Enum\CmsProvider;
use App\Exception\CmsIntegrationException;

final class CmsConnectionConfigurator
{
    public function __construct(private readonly CmsCredentialEncryption $credentialEncryption) {}

    /**
     * @param array<string, mixed> $values
     */
    public function configure(CmsConnection $connection, array $values): void
    {
        $connection->setBaseUrl($this->normalizeBaseUrl($connection->getBaseUrl()));

        match ($connection->getProvider()) {
            CmsProvider::WORDPRESS => $this->configureWordPress($connection, $values),
            CmsProvider::SHOPIFY => $this->configureShopify($connection, $values),
            default => throw new CmsIntegrationException(sprintf('%s connections are not implemented yet.', $connection->getProvider()->value)),
        };
    }

    /** @param array<string, mixed> $values */
    private function configureWordPress(CmsConnection $connection, array $values): void
    {
        $username = $this->stringValue($values['username'] ?? null);
        $applicationPassword = $this->stringValue($values['applicationPassword'] ?? null);

        if (null === $username) {
            throw new CmsIntegrationException('Enter the WordPress username linked to the application password.');
        }

        if (!str_starts_with($connection->getBaseUrl(), 'https://')) {
            throw new CmsIntegrationException('WordPress Application Passwords require HTTPS. Please use an https:// URL.');
        }

        if (null !== $applicationPassword) {
            $connection->setEncryptedAccessToken($this->credentialEncryption->encrypt($applicationPassword));
        } elseif (null === $connection->getEncryptedAccessToken()) {
            throw new CmsIntegrationException('Enter a WordPress application password.');
        }

        $connection
            ->setEncryptedApiKey(null)
            ->setEncryptedRefreshToken(null)
            ->setSettings([
                'username' => $username,
                'post_type' => 'posts',
            ]);
    }

    /** @param array<string, mixed> $values */
    private function configureShopify(CmsConnection $connection, array $values): void
    {
        $accessToken = $this->stringValue($values['accessToken'] ?? null);
        $blogId = $this->stringValue($values['shopifyBlogId'] ?? null);
        $authorName = $this->stringValue($values['authorName'] ?? null);
        $existingSettings = $connection->getSettings() ?? [];
        $blogId ??= $this->stringValue($existingSettings['blog_id'] ?? null);

        if (null !== $accessToken) {
            $connection->setEncryptedAccessToken($this->credentialEncryption->encrypt($accessToken));
        } elseif (null === $connection->getEncryptedAccessToken()) {
            throw new CmsIntegrationException('Enter a Shopify Admin API access token.');
        }

        $connection
            ->setEncryptedApiKey(null)
            ->setEncryptedRefreshToken(null)
            ->setSettings(array_filter([
                'blog_id' => $blogId,
                'author_name' => $authorName ?? 'SEO GEO AI',
                'blog_title' => $existingSettings['blog_title'] ?? null,
                'available_blogs' => $existingSettings['available_blogs'] ?? null,
            ], static fn(mixed $value): bool => null !== $value));
    }

    private function normalizeBaseUrl(string $baseUrl): string
    {
        $baseUrl = rtrim(trim($baseUrl), '/');
        $parts = parse_url($baseUrl);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (!in_array($scheme, ['http', 'https'], true) || '' === $host) {
            throw new CmsIntegrationException('The CMS base URL must be a complete http or https URL.');
        }

        return $baseUrl;
    }

    private function stringValue(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return '' === $value ? null : $value;
    }
}
