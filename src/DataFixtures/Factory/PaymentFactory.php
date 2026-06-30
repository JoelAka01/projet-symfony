<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\Payment;
use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\PaymentStatus;
use App\Enum\SubscriptionPlan;
use Doctrine\Persistence\ObjectManager;

final class PaymentFactory
{
    public static function create(
        ObjectManager $manager,
        ?User $user,
        ?Subscription $subscription,
        SubscriptionPlan $plan,
        PaymentStatus $status,
        int $amountCents,
        ?\DateTimeImmutable $paidAt = null,
        ?string $adminNote = null,
    ): Payment {
        $payment = new Payment();
        $payment
            ->setUser($user)
            ->setSubscription($subscription)
            ->setPlan($plan)
            ->setStatus($status)
            ->setAmountCents($amountCents)
            ->setCurrency('EUR')
            ->setCardLastFour(sprintf('%04d', random_int(1000, 9999)))
            ->setSimulated(true);

        if ($paidAt !== null) {
            $payment->setPaidAt($paidAt);
        }

        if ($adminNote !== null) {
            $payment->setAdminNote($adminNote);
        }

        $manager->persist($payment);

        return $payment;
    }
}
