<?php

declare(strict_types=1);

namespace App\Tests\Unit\Form;

use App\Entity\CmsConnection;
use App\Enum\CmsProvider;
use App\Form\CmsConnectionType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

final class CmsConnectionTypeTest extends TypeTestCase
{
    public function testItDynamicallyValidatesShopifyFieldsOnSubmit(): void
    {
        $connection = new CmsConnection();
        $form = $this->factory->create(CmsConnectionType::class, $connection);
        $form->submit([
            'provider' => CmsProvider::SHOPIFY->value,
            'baseUrl' => 'https://store.myshopify.com',
            'username' => '',
            'applicationPassword' => '',
            'accessToken' => 'real-admin-token',
            'shopifyBlogId' => '',
            'authorName' => 'SEO Team',
        ]);

        self::assertTrue($form->isSubmitted());
        self::assertTrue($form->isValid(), (string) $form->getErrors(true, false));
        self::assertSame(CmsProvider::SHOPIFY, $connection->getProvider());
        self::assertNull($form->get('shopifyBlogId')->getData());
    }

    public function testEditingKeepsTheStoredTokenAndOffersDiscoveredBlogs(): void
    {
        $connection = new CmsConnection();
        $connection
            ->setProvider(CmsProvider::SHOPIFY)
            ->setBaseUrl('https://store.myshopify.com')
            ->setEncryptedAccessToken('encrypted-token')
            ->setSettings([
                'blog_id' => 'gid://shopify/Blog/123',
                'author_name' => 'SEO Team',
                'available_blogs' => [
                    ['id' => 'gid://shopify/Blog/123', 'title' => 'News'],
                    ['id' => 'gid://shopify/Blog/456', 'title' => 'Guides'],
                ],
            ]);

        $form = $this->factory->create(CmsConnectionType::class, $connection);
        $form->submit([
            'provider' => CmsProvider::SHOPIFY->value,
            'baseUrl' => 'https://store.myshopify.com',
            'username' => '',
            'applicationPassword' => '',
            'accessToken' => '',
            'shopifyBlogId' => 'gid://shopify/Blog/456',
            'authorName' => 'SEO Team',
        ]);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true, false));
        self::assertSame('gid://shopify/Blog/456', $form->get('shopifyBlogId')->getData());
    }

    public function testChangingProviderRequiresTheNewProviderCredential(): void
    {
        $connection = new CmsConnection();
        $connection
            ->setProvider(CmsProvider::WORDPRESS)
            ->setBaseUrl('https://example.com')
            ->setEncryptedAccessToken('encrypted-wordpress-password')
            ->setSettings(['username' => 'publisher']);

        $form = $this->factory->create(CmsConnectionType::class, $connection);
        $form->submit([
            'provider' => CmsProvider::SHOPIFY->value,
            'baseUrl' => 'https://store.myshopify.com',
            'username' => '',
            'applicationPassword' => '',
            'accessToken' => '',
            'shopifyBlogId' => '',
            'authorName' => 'SEO Team',
        ]);

        self::assertFalse($form->isValid());
        self::assertGreaterThan(0, $form->get('accessToken')->getErrors()->count());
    }

    protected function getExtensions(): array
    {
        return [
            new ValidatorExtension(Validation::createValidator()),
        ];
    }
}
