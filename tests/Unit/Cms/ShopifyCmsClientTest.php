<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cms;

use App\Entity\Article;
use App\Entity\CmsConnection;
use App\Entity\Keyword;
use App\Enum\CmsProvider;
use App\Service\Cms\CmsContentRenderer;
use App\Service\Cms\CmsCredentialEncryption;
use App\Service\Cms\ShopifyCmsClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ShopifyCmsClientTest extends TestCase
{
    public function testItDiscoversTheDestinationBlogDuringConnectionTest(): void
    {
        $encryption = new CmsCredentialEncryption(str_repeat('s', 64));
        $connection = new CmsConnection();
        $connection
            ->setProvider(CmsProvider::SHOPIFY)
            ->setBaseUrl('https://store.myshopify.com')
            ->setSettings(['author_name' => 'SEO Team'])
            ->setEncryptedAccessToken($encryption->encrypt('shopify-token'));

        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'data' => [
                'shop' => [
                    'name' => 'Example Store',
                    'myshopifyDomain' => 'store.myshopify.com',
                ],
                'blogs' => [
                    'nodes' => [
                        [
                            'id' => 'gid://shopify/Blog/123',
                            'title' => 'News',
                            'handle' => 'news',
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR), ['http_code' => 200]));

        $client = new ShopifyCmsClient(
            $httpClient,
            $encryption,
            new CmsContentRenderer(),
            '2026-04',
            30,
        );

        $result = $client->testConnection($connection);

        self::assertSame('gid://shopify/Blog/123', $result->details['blog_id']);
        self::assertSame('News', $result->details['blog_title']);
        self::assertCount(1, $result->details['available_blogs']);
    }

    public function testItCreatesAShopifyBlogArticleWithKeywords(): void
    {
        $encryption = new CmsCredentialEncryption(str_repeat('s', 64));
        $connection = new CmsConnection();
        $connection
            ->setProvider(CmsProvider::SHOPIFY)
            ->setBaseUrl('https://store.myshopify.com')
            ->setSettings([
                'blog_id' => 'gid://shopify/Blog/123',
                'author_name' => 'SEO Team',
            ])
            ->setEncryptedAccessToken($encryption->encrypt('shopify-token'));

        $primaryKeyword = (new Keyword())->setTerm('technical seo');
        $article = new Article();
        $article
            ->setTitle('Technical SEO guide')
            ->setSeoDescription('A practical technical SEO guide.')
            ->setSlug('technical-seo-guide')
            ->setContentHtml('<p>Useful guide.</p>')
            ->setPrimaryKeyword($primaryKeyword)
            ->addTargetKeyword($primaryKeyword);

        $httpClient = new MockHttpClient(
            static function (string $method, string $url, array $options): MockResponse {
                self::assertSame('POST', $method);
                self::assertSame('https://store.myshopify.com/admin/api/2026-04/graphql.json', $url);
                self::assertContains('X-Shopify-Access-Token: shopify-token', $options['headers']);

                $payload = json_decode((string) $options['body'], true, 512, JSON_THROW_ON_ERROR);
                self::assertSame('gid://shopify/Blog/123', $payload['variables']['article']['blogId']);
                self::assertSame(['technical seo'], $payload['variables']['article']['tags']);
                self::assertTrue($payload['variables']['article']['isPublished']);

                return new MockResponse(json_encode([
                    'data' => [
                        'articleCreate' => [
                            'article' => [
                                'id' => 'gid://shopify/Article/456',
                                'handle' => 'technical-seo-guide',
                                'blog' => ['handle' => 'news'],
                            ],
                            'userErrors' => [],
                        ],
                    ],
                ], JSON_THROW_ON_ERROR), ['http_code' => 200]);
            },
        );

        $client = new ShopifyCmsClient(
            $httpClient,
            $encryption,
            new CmsContentRenderer(),
            '2026-04',
            30,
        );

        $result = $client->publishArticle($connection, $article, null, true);

        self::assertSame('gid://shopify/Article/456', $result->externalId);
        self::assertSame('https://store.myshopify.com/blogs/news/technical-seo-guide', $result->externalUrl);
    }
}
