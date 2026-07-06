<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminDashboardTest extends WebTestCase
{
    public function testAdminAreaRequiresAuthentication(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin');

        self::assertResponseRedirects('/login');
    }

    public function testRegularUserCannotOpenAdminArea(): void
    {
        $client = self::createClient();
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'user@example.com']);
        self::assertInstanceOf(User::class, $user);

        $client->loginUser($user);
        $client->request('GET', '/admin');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdministratorCanManageUsersAndProjects(): void
    {
        $client = self::createClient();
        $admin = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'admin@example.com']);
        self::assertInstanceOf(User::class, $admin);

        $client->loginUser($admin);

        $client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Platform overview');
        self::assertSelectorTextContains('body', 'AI credits consumed');
        self::assertSelectorNotExists('.nav-upgrade-btn');
        self::assertSelectorExists('.brand[href="/admin"]');

        $client->request('GET', '/admin/users');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Users and roles');
        self::assertSelectorTextContains('body', 'admin@example.com');
        self::assertSelectorTextContains('table.admin-table', 'Subscription');

        $client->request('GET', '/admin/users/new');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Create user');

        $client->request('GET', '/admin/projects');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'All projects');

        $client->request('GET', '/admin/projects/new');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Create project');

        $client->request('GET', '/admin/payments');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Simulated payments');

        $client->request('GET', '/admin/pipeline-settings');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Pipeline Settings');
        self::assertSelectorTextContains('body', 'SERP Intelligence');
        self::assertSelectorTextContains('body', 'Article Generation');
        self::assertSelectorTextContains('body', 'Required');
    }

    public function testRegularUserHasUpgradeButtonAndLogoRedirectsToProjects(): void
    {
        $client = self::createClient();
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'user@example.com']);
        self::assertInstanceOf(User::class, $user);

        $client->loginUser($user);
        $client->request('GET', '/projects');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.nav-upgrade-btn');
        self::assertSelectorExists('.brand[href="/projects"]');
    }
}
