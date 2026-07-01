<?php

declare(strict_types=1);

namespace App\DataFixtures\Billing;

use App\DataFixtures\Core\UserFixtures;
use App\DataFixtures\Helper\FixtureConfig;
use App\DataFixtures\Helper\FixtureReference;
use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\SubscriptionPlan;
use App\Enum\SubscriptionStatus;
use App\Service\Billing\PlanCatalog;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Génère 20 abonnements cohérents avec les scénarios métier.
 *
 * Dépend de :
 * - UserFixtures
 *
 * Références créées :
 * - subscription-0 à subscription-19
 *
 * Scénarios :
 * - admin   : PRO ACTIVE
 * - manager : STARTER ACTIVE
 * - user-8 (WebPulse owner) : EXPERT ACTIVE
 * - user@seo-ai.test : STARTER EXPIRED (en difficulté)
 */
final class SubscriptionFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function __construct(
        private readonly PlanCatalog $planCatalog,
    ) {}

    public static function getGroups(): array
    {
        return ['billing', 'demo', 'test'];
    }

    public function getDependencies(): array
    {
        return [UserFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $subscriptionIndex = 0;

        // ── Abonnements fixes des comptes démo ─────────────────────────
        $demoSubscriptions = [
            [FixtureReference::USER_ADMIN, SubscriptionPlan::PRO, SubscriptionStatus::ACTIVE, 0],
            [FixtureReference::USER_MANAGER, SubscriptionPlan::STARTER, SubscriptionStatus::ACTIVE, 0],
            [FixtureReference::USER_USER, SubscriptionPlan::STARTER, SubscriptionStatus::EXPIRED, 2],
            [FixtureReference::user(8), SubscriptionPlan::EXPERT, SubscriptionStatus::ACTIVE, 0],
        ];

        foreach ($demoSubscriptions as [$userRef, $plan, $status, $monthsAgo]) {
            $user = $this->getReference($userRef, User::class);
            $sub = $this->createSubscription($manager, $user, $plan, $status, $monthsAgo);
            $this->addReference(FixtureReference::subscription($subscriptionIndex++), $sub);
        }

        // ── Abonnements Faker pour le reste ────────────────────────────
        $plans = [SubscriptionPlan::STARTER, SubscriptionPlan::PRO, SubscriptionPlan::EXPERT];
        $planWeights = [
            SubscriptionPlan::STARTER, SubscriptionPlan::STARTER, SubscriptionPlan::STARTER,
            SubscriptionPlan::STARTER, SubscriptionPlan::STARTER, SubscriptionPlan::STARTER,
            SubscriptionPlan::PRO, SubscriptionPlan::PRO, SubscriptionPlan::PRO,
            SubscriptionPlan::PRO, SubscriptionPlan::PRO, SubscriptionPlan::PRO,
            SubscriptionPlan::EXPERT, SubscriptionPlan::EXPERT, SubscriptionPlan::EXPERT,
            SubscriptionPlan::EXPERT,
        ];

        $statusWeights = [
            SubscriptionStatus::ACTIVE, SubscriptionStatus::ACTIVE, SubscriptionStatus::ACTIVE,
            SubscriptionStatus::ACTIVE, SubscriptionStatus::ACTIVE, SubscriptionStatus::ACTIVE,
            SubscriptionStatus::ACTIVE, SubscriptionStatus::ACTIVE, SubscriptionStatus::ACTIVE,
            SubscriptionStatus::ACTIVE,
            SubscriptionStatus::CANCELED, SubscriptionStatus::CANCELED, SubscriptionStatus::CANCELED,
            SubscriptionStatus::CANCELED,
            SubscriptionStatus::EXPIRED, SubscriptionStatus::EXPIRED,
        ];

        $remaining = FixtureConfig::SUBSCRIPTIONS - $subscriptionIndex;
        for ($i = 0; $i < $remaining; ++$i) {
            $userIdx = 3 + ($i % (FixtureConfig::USERS - FixtureConfig::DEMO_USERS));
            $user = $this->getReference(FixtureReference::user($userIdx), User::class);

            $plan = $planWeights[array_rand($planWeights)];
            $status = $statusWeights[array_rand($statusWeights)];
            $monthsAgo = match ($status) {
                SubscriptionStatus::ACTIVE => 0,
                SubscriptionStatus::CANCELED => random_int(1, 4),
                SubscriptionStatus::EXPIRED => random_int(2, 6),
            };

            $sub = $this->createSubscription($manager, $user, $plan, $status, $monthsAgo);
            $this->addReference(FixtureReference::subscription($subscriptionIndex++), $sub);
        }

        $manager->flush();
    }

    private function createSubscription(
        ObjectManager $manager,
        User $user,
        SubscriptionPlan $plan,
        SubscriptionStatus $status,
        int $monthsAgo,
    ): Subscription {
        $details = $this->planCatalog->get($plan);
        $startsAt = new \DateTimeImmutable(sprintf('-%d months', $monthsAgo));
        $endsAt = $startsAt->modify('+1 month');

        $subscription = new Subscription();
        $subscription
            ->setUser($user)
            ->setPlan($plan)
            ->setStatus($status)
            ->setMonthlyPriceCents($details['priceCents'])
            ->setMonthlyCreditLimit($details['monthlyCredits'])
            ->setWeeklyAnalysisLimit($details['weeklyAnalyses'])
            ->setStartsAt($startsAt)
            ->setEndsAt($endsAt);

        $manager->persist($subscription);

        return $subscription;
    }
}
