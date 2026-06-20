<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Repository\AnalysisQuotaUsageRepository;
use App\Repository\SubscriptionRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class SubscriptionExtension extends AbstractExtension
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly AnalysisQuotaUsageRepository $quotaUsageRepository,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('has_active_subscription', [$this, 'hasActiveSubscription']),
            new TwigFunction('has_achieved_weekly_limit', [$this, 'hasAchievedWeeklyLimit']),
        ];
    }

    public function hasActiveSubscription(?User $user): bool
    {
        if (null === $user) {
            return false;
        }

        return null !== $this->subscriptionRepository->findActiveForUser($user);
    }

    public function hasAchievedWeeklyLimit(?User $user): bool
    {
        if (null === $user) {
            return false;
        }

        $subscription = $this->subscriptionRepository->findActiveForUser($user);
        if (null === $subscription) {
            return false;
        }

        $weekStartedAt = new \DateTimeImmutable('monday this week 00:00:00');
        if ($weekStartedAt < $subscription->getStartsAt()) {
            $weekStartedAt = $subscription->getStartsAt();
        }

        $weeklyUsed = $this->quotaUsageRepository->countForUserSince($user, $weekStartedAt);

        return $weeklyUsed >= $subscription->getWeeklyAnalysisLimit();
    }
}
