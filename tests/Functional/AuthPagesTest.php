<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AuthPagesTest extends WebTestCase
{
    public function testAuthPagesAreAccessible(): void
    {
        $client = self::createClient();

        $client->request('GET', '/login');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Login');

        $client->request('GET', '/register');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Register');

        $client->request('GET', '/password-reset');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Reset your password');

        $client->request('GET', '/verify-email/resend');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Resend verification email');
    }

    public function testDashboardRequiresAuthentication(): void
    {
        $client = self::createClient();

        $client->request('GET', '/dashboard');

        self::assertResponseRedirects('/login');
    }
}
