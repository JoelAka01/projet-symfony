<?php

declare(strict_types=1);

namespace App\Service\Billing;

use App\Entity\Payment;
use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\PaymentStatus;
use App\Enum\SubscriptionPlan;
use App\Enum\SubscriptionStatus;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;

final class SubscriptionManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly PlanCatalog $planCatalog,
    ) {}

    public function purchase(User $user, SubscriptionPlan $plan, string $cardNumber): Payment
    {
        foreach ($this->subscriptionRepository->findActiveSubscriptionsForUser($user) as $currentSubscription) {
            $currentSubscription->setStatus(SubscriptionStatus::CANCELED);
        }

        $details = $this->planCatalog->get($plan);
        $now = new \DateTimeImmutable();

        $subscription = new Subscription();
        $subscription
            ->setUser($user)
            ->setPlan($plan)
            ->setStatus(SubscriptionStatus::ACTIVE)
            ->setMonthlyPriceCents($details['priceCents'])
            ->setMonthlyCreditLimit($details['monthlyCredits'])
            ->setWeeklyAnalysisLimit($details['weeklyAnalyses'])
            ->setStartsAt($now)
            ->setEndsAt($now->modify('+1 month'));

        $payment = new Payment();
        $payment
            ->setUser($user)
            ->setSubscription($subscription)
            ->setPlan($plan)
            ->setStatus(PaymentStatus::PAID)
            ->setAmountCents($details['priceCents'])
            ->setCurrency('EUR')
            ->setCardLastFour($cardNumber)
            ->setSimulated(true)
            ->setPaidAt($now);

        $this->entityManager->persist($subscription);
        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        return $payment;
    }

    public function synchronizePayment(Payment $payment): void
    {
        $subscription = $payment->getSubscription();
        if (null === $subscription) {
            $this->entityManager->flush();

            return;
        }

        if (PaymentStatus::PAID === $payment->getStatus()) {
            $user = $subscription->getUser();
            if ($user instanceof User) {
                foreach ($this->subscriptionRepository->findActiveSubscriptionsForUser($user) as $activeSubscription) {
                    if ($activeSubscription !== $subscription) {
                        $activeSubscription->setStatus(SubscriptionStatus::CANCELED);
                    }
                }
            }
            $subscription->setStatus(SubscriptionStatus::ACTIVE);
            if ($subscription->getEndsAt() <= new \DateTimeImmutable()) {
                $subscription
                    ->setStartsAt(new \DateTimeImmutable())
                    ->setEndsAt(new \DateTimeImmutable('+1 month'));
            }
            $payment->setPaidAt($payment->getPaidAt() ?? new \DateTimeImmutable());
        } else {
            $subscription->setStatus(SubscriptionStatus::CANCELED);
        }

        $this->entityManager->flush();
    }
}
