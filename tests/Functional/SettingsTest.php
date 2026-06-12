<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SettingsTest extends WebTestCase
{
    public function testSettingsRequiresAuthentication(): void
    {
        $client = self::createClient();
        $client->request('GET', '/settings');

        self::assertResponseRedirects('/login');
    }

    public function testSettingsPageLoadsForAuthenticatedUser(): void
    {
        $client = self::createClient();
        $userRepository = self::getContainer()->get(UserRepository::class);

        // Find a test user (seeded by fixtures)
        $user = $userRepository->findOneBy(['email' => 'user@example.com']);
        if (!$user instanceof User) {
            $this->markTestSkipped('Fixture user user@example.com not found.');
        }

        $client->loginUser($user);
        $client->request('GET', '/settings');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Account Settings');
        self::assertSelectorTextContains('body', 'Profile Details');
        self::assertSelectorTextContains('body', 'Account Security');
    }
}
