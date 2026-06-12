<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomepageTest extends WebTestCase
{
    public function testHomepageIsAccessible(): void
    {
        $client = self::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Get found on Google. Get recommended by AI.');
        self::assertSelectorTextContains('nav', 'Pricing');
        self::assertSelectorTextContains('body', 'Start with 3 free analyses');
    }

    public function testHomepageRedirectsWhenAuthenticated(): void
    {
        $client = self::createClient();
        $userRepository = self::getContainer()->get(UserRepository::class);

        $user = $userRepository->findOneBy(['email' => 'user@example.com']);
        if (!$user instanceof User) {
            $this->markTestSkipped('Fixture user user@example.com not found.');
        }

        $client->loginUser($user);
        $client->request('GET', '/');

        self::assertResponseRedirects('/projects');
    }
}
