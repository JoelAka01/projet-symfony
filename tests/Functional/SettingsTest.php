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
    }

    public function testSubscribedUserSettingsPageDisplaysSubscriptionAndAllowsCancellation(): void
    {
        $client = self::createClient();
        $container = self::getContainer();
        $userRepository = $container->get(UserRepository::class);
        $entityManager = $container->get(\Doctrine\ORM\EntityManagerInterface::class);

        $user = $userRepository->findOneBy(['email' => 'user@example.com']);
        if (!$user instanceof User) {
            $this->markTestSkipped('Fixture user user@example.com not found.');
        }

        // Add a subscription first to be sure
        $subscription = new \App\Entity\Subscription();
        $subscription->setUser($user)
            ->setPlan(\App\Enum\SubscriptionPlan::STARTER)
            ->setStatus(\App\Enum\SubscriptionStatus::ACTIVE)
            ->setMonthlyPriceCents(900)
            ->setMonthlyCreditLimit(1000000)
            ->setWeeklyAnalysisLimit(5);

        $entityManager->persist($subscription);
        $entityManager->flush();

        $client->loginUser($user);
        $crawler = $client->request('GET', '/settings');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Subscription Plan');
        self::assertSelectorTextContains('body', 'Starter Plan');
        self::assertSelectorTextContains('body', 'Cancel Subscription');

        // Cancel the subscription
        $client->submitForm('Cancel Subscription');

        self::assertResponseRedirects('/settings');
        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'canceled successfully');

        // Assert subscription is CANCELED in DB
        $subRepo = self::getContainer()->get(\App\Repository\SubscriptionRepository::class);
        $freshSubscription = $subRepo->findOneBy(['id' => $subscription->getId()]);
        self::assertNotNull($freshSubscription);
        self::assertSame(\App\Enum\SubscriptionStatus::CANCELED, $freshSubscription->getStatus());

        // Clean up to prevent side effects on other tests
        $freshEntityManager = self::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $freshEntityManager->remove($freshSubscription);
        $freshEntityManager->flush();
    }
}
