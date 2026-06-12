<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Payment;
use App\Entity\User;
use App\Enum\PaymentStatus;
use App\Enum\SubscriptionPlan;
use App\Repository\PaymentRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class BillingTest extends WebTestCase
{
    public function testPricingPageIsPublic(): void
    {
        $client = self::createClient();
        $client->request('GET', '/pricing');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Choose your analysis plan');
        self::assertSelectorTextContains('body', '3 free analyses');
        self::assertSelectorTextContains('body', 'Starter');
        self::assertSelectorTextContains('body', 'Pro');
        self::assertSelectorTextContains('body', 'Expert');
    }

    public function testCheckoutRequiresAuthentication(): void
    {
        $client = self::createClient();
        $client->request('GET', '/billing/checkout/starter');

        self::assertResponseRedirects('/login');
    }

    public function testAuthenticatedUserCanCompleteSimulatedPayment(): void
    {
        $client = self::createClient();
        $container = self::getContainer();
        $user = $container->get(UserRepository::class)->findOneBy(['email' => 'user@example.com']);
        self::assertInstanceOf(User::class, $user);

        $client->loginUser($user);
        $crawler = $client->request('GET', '/billing/checkout/starter');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Activate for €9/month')->form([
            'checkout[cardholder]' => 'Test User',
            'checkout[cardNumber]' => '4242 4242 4242 4242',
            'checkout[expiry]' => '12/30',
            'checkout[cvc]' => '123',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/pricing');

        $subscription = $container->get(SubscriptionRepository::class)->findActiveForUser($user);
        self::assertNotNull($subscription);
        self::assertSame(SubscriptionPlan::STARTER, $subscription->getPlan());

        $payment = $container->get(PaymentRepository::class)->findOneBy(
            ['user' => $user],
            ['createdAt' => 'DESC'],
        );
        self::assertInstanceOf(Payment::class, $payment);
        self::assertSame(PaymentStatus::PAID, $payment->getStatus());
        self::assertTrue($payment->isSimulated());
        self::assertSame('4242', $payment->getCardLastFour());
    }
}
