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

        // Clean up
        $freshEntityManager = self::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $subRepo = self::getContainer()->get(SubscriptionRepository::class);
        $freshSub = $subRepo->findActiveForUser($user);
        if ($freshSub) {
            $freshEntityManager->remove($freshSub);
        }
        $paymentRepo = self::getContainer()->get(PaymentRepository::class);
        $freshPayment = $paymentRepo->findOneBy(['id' => $payment->getId()]);
        if ($freshPayment) {
            $freshEntityManager->remove($freshPayment);
        }
        $freshEntityManager->flush();
    }

    public function testSubscribedUserUpgradeButtonVisibility(): void
    {
        $client = self::createClient();
        $container = self::getContainer();
        $userRepository = $container->get(UserRepository::class);
        $entityManager = $container->get(\Doctrine\ORM\EntityManagerInterface::class);

        $user = $userRepository->findOneBy(['email' => 'user@example.com']);
        self::assertInstanceOf(User::class, $user);

        // Add a subscription first
        $subscription = new \App\Entity\Subscription();
        $subscription->setUser($user)
            ->setPlan(SubscriptionPlan::STARTER)
            ->setStatus(\App\Enum\SubscriptionStatus::ACTIVE)
            ->setMonthlyPriceCents(900)
            ->setMonthlyCreditLimit(1000000)
            ->setWeeklyAnalysisLimit(5);

        $entityManager->persist($subscription);
        $entityManager->flush();

        $client->loginUser($user);
        $client->request('GET', '/projects');

        self::assertResponseIsSuccessful();
        // The upgrade button should NOT exist on the navbar
        self::assertSelectorNotExists('.nav-upgrade-btn');
        // The upgrade button should exist in the profile dropdown menu
        self::assertSelectorExists('.profile-dropdown-menu a[href="/pricing"]');

        // Now test: what if they achieved their weekly limit?
        // Let's retrieve the user's project and domain
        $project = $container->get(\App\Repository\ProjectRepository::class)->findOneBy(['owner' => $user]);
        self::assertInstanceOf(\App\Entity\Project::class, $project);
        $domain = $project->getDomains()->first();
        self::assertInstanceOf(\App\Entity\Domain::class, $domain);

        // Create a dummy audit
        $audit = new \App\Entity\Audit();
        $audit->setProject($project)
            ->setDomain($domain)
            ->setStatus(\App\Enum\AuditStatus::COMPLETED)
            ->setSeoScore(90);
        $entityManager->persist($audit);
        $entityManager->flush();

        // Create 5 consumption records to hit the weekly limit of 5
        for ($i = 0; $i < 5; ++$i) {
            $usage = new \App\Entity\AnalysisQuotaUsage();
            $usage->setUser($user)
                ->setProject($project)
                ->setAudit($audit)
                ->setSubscription($subscription)
                ->setPlanCode('STARTER')
                ->setCreditsCharged(10000)
                ->setStatus(\App\Enum\AnalysisQuotaStatus::CONSUMED);
            $entityManager->persist($usage);
        }
        $entityManager->flush();

        // Request again to verify the Upgrade button appears on navbar now
        $client->request('GET', '/projects');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.nav-upgrade-btn');

        // Clean up
        $freshEntityManager = self::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $subRepo = self::getContainer()->get(SubscriptionRepository::class);
        $freshSub = $subRepo->findOneBy(['id' => $subscription->getId()]);
        if ($freshSub) {
            $freshEntityManager->remove($freshSub);
        }
        $usages = self::getContainer()->get(\App\Repository\AnalysisQuotaUsageRepository::class)->findBy(['user' => $user]);
        foreach ($usages as $u) {
            $freshEntityManager->remove($u);
        }
        $freshAudit = $freshEntityManager->find(\App\Entity\Audit::class, $audit->getId());
        if ($freshAudit) {
            $freshEntityManager->remove($freshAudit);
        }
        $freshEntityManager->flush();
    }

    public function testSendPaymentReceiptEmail(): void
    {
        $client = self::createClient();
        $container = self::getContainer();
        $user = $container->get(UserRepository::class)->findOneBy(['email' => 'user@example.com']);
        self::assertInstanceOf(User::class, $user);

        $payment = new Payment();
        $payment->setUser($user);
        $payment->setAmountCents(900);
        $payment->setCardLastFour('4242');
        $payment->setPaidAt(new \DateTimeImmutable());

        $billingEmailService = $container->get(\App\Service\Billing\BillingEmailService::class);
        $billingEmailService->sendPaymentReceiptEmail($user, $payment);

        $emails = self::getMailerMessages();

        $userReceipt = null;
        $adminReceipt = null;

        foreach ($emails as $email) {
            self::assertInstanceOf(\Symfony\Component\Mime\Email::class, $email);
            $subject = $email->getSubject();
            if (str_contains($subject, '[Admin] Payment received')) {
                $adminReceipt = $email;
            } elseif (str_contains($subject, 'Payment Receipt')) {
                $userReceipt = $email;
            }
        }

        self::assertNotNull($userReceipt, 'User receipt email was not sent.');
        self::assertNotNull($adminReceipt, 'Admin receipt notification was not sent.');
    }
}
