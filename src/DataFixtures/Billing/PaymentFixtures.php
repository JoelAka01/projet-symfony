<?php

declare(strict_types=1);

namespace App\DataFixtures\Billing;

use App\DataFixtures\Factory\PaymentFactory;
use App\DataFixtures\Helper\FixtureConfig;
use App\DataFixtures\Helper\FixtureReference;
use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\PaymentStatus;
use App\Enum\SubscriptionStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Génère 50 paiements liés aux abonnements.
 *
 * Dépend de :
 * - SubscriptionFixtures
 *
 * Cohérence :
 * - Abonnements ACTIVE → paiements PAID
 * - Abonnements EXPIRED/CANCELED → mix PAID/CANCELED/REFUNDED
 */
final class PaymentFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['billing', 'demo'];
    }

    public function getDependencies(): array
    {
        return [SubscriptionFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $paymentIndex = 0;

        for ($s = 0; $s < FixtureConfig::SUBSCRIPTIONS; $s++) {
            $subscription = $this->getReference(FixtureReference::subscription($s), Subscription::class);
            $user = $subscription->getUser();
            $plan = $subscription->getPlan();
            $amountCents = $subscription->getMonthlyPriceCents();

            // 2-3 paiements par abonnement
            $nbPayments = min(FixtureConfig::PAYMENTS - $paymentIndex, random_int(2, 3));

            for ($p = 0; $p < $nbPayments; $p++) {
                if ($paymentIndex >= FixtureConfig::PAYMENTS) {
                    break 2;
                }

                $monthsAgo = $nbPayments - $p;
                $paidAt = new \DateTimeImmutable(sprintf('-%d months', $monthsAgo));

                // Cohérence : dernier paiement = CANCELED pour abonnements expirés
                $status = PaymentStatus::PAID;
                if ($subscription->getStatus() === SubscriptionStatus::EXPIRED && $p === $nbPayments - 1) {
                    $status = PaymentStatus::CANCELED;
                    $paidAt = null;
                } elseif ($subscription->getStatus() === SubscriptionStatus::CANCELED && $p === $nbPayments - 1 && random_int(0, 100) < 30) {
                    $status = PaymentStatus::REFUNDED;
                }

                $adminNote = match ($status) {
                    PaymentStatus::CANCELED => 'Paiement annulé — abonnement expiré.',
                    PaymentStatus::REFUNDED => 'Remboursement suite à annulation anticipée.',
                    default => 'Paiement mensuel fixture démo.',
                };

                PaymentFactory::create($manager, $user, $subscription, $plan, $status, $amountCents, $paidAt, $adminNote);
                $paymentIndex++;
            }
        }

        $manager->flush();
    }
}
