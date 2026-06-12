<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cms;

use App\Entity\Article;
use App\Entity\CmsConnection;
use App\Service\Cms\CmsContentRenderer;
use App\Service\Cms\CmsCredentialEncryption;
use App\Service\Cms\ExternalImageFetcher;
use App\Service\Cms\WordPressCmsClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class WordPressCmsClientTest extends TestCase
{
    public function testItPublishesARealWordPressPostPayload(): void
    {
        $encryption = new CmsCredentialEncryption(str_repeat('w', 64));
        $connection = new CmsConnection();
        $connection
            ->setBaseUrl('https://wordpress.example')
            ->setSettings(['username' => 'editor'])
            ->setEncryptedAccessToken($encryption->encrypt('application-password'));

        $article = new Article();
        $article
            ->setTitle('Visible title')
            ->setSeoTitle('SEO title')
            ->setSeoDescription('Search description')
            ->setSlug('seo-title')
            ->setContentHtml('<p>Useful article body.</p>');

        $httpClient = new MockHttpClient(
            static function (string $method, string $url, array $options): MockResponse {
                self::assertSame('POST', $method);
                self::assertSame('https://wordpress.example/wp-json/wp/v2/posts', $url);
                self::assertContains(
                    'Authorization: Basic ' . base64_encode('editor:application-password'),
                    $options['headers'],
                );

                $payload = json_decode((string) $options['body'], true, 512, JSON_THROW_ON_ERROR);
                self::assertSame('SEO title', $payload['title']);
                self::assertSame('Search description', $payload['excerpt']);
                self::assertSame('seo-title', $payload['slug']);
                self::assertSame('publish', $payload['status']);

                return new MockResponse(json_encode([
                    'id' => 42,
                    'link' => 'https://wordpress.example/seo-title/',
                    'status' => 'publish',
                ], JSON_THROW_ON_ERROR), ['http_code' => 201]);
            },
        );

        $client = new WordPressCmsClient(
            $httpClient,
            $encryption,
            new ExternalImageFetcher($httpClient, 30, 8_388_608),
            new CmsContentRenderer(),
            30,
        );

        $result = $client->publishArticle($connection, $article, null, true);

        self::assertSame('42', $result->externalId);
        self::assertSame('https://wordpress.example/seo-title/', $result->externalUrl);
        self::assertNotEmpty($result->warnings);
    }
}
