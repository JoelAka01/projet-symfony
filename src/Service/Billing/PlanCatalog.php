<?php

declare(strict_types=1);

namespace App\Service\Billing;

use App\Enum\SubscriptionPlan;

final class PlanCatalog
{
    public const ESTIMATED_CREDITS_PER_ANALYSIS = 40000;
    private const BILLING_WEEKS = 4;
    private const CREDITS_PER_PRICE_CENT = 1000;

    /** @return list<array{plan: SubscriptionPlan, name: string, priceCents: int, monthlyCredits: int, weeklyAnalyses: int, featured: bool}> */
    public function all(): array
    {
        return array_map(fn(SubscriptionPlan $plan): array => $this->get($plan), SubscriptionPlan::cases());
    }

    /** @return array{plan: SubscriptionPlan, name: string, priceCents: int, monthlyCredits: int, weeklyAnalyses: int, featured: bool} */
    public function get(SubscriptionPlan $plan): array
    {
        $priceCents = match ($plan) {
            SubscriptionPlan::STARTER => 900,
            SubscriptionPlan::PRO => 2400,
            SubscriptionPlan::EXPERT => 4900,
        };
        $monthlyCredits = $priceCents * self::CREDITS_PER_PRICE_CENT;
        $weeklyAnalyses = max(
            1,
            intdiv($monthlyCredits, self::ESTIMATED_CREDITS_PER_ANALYSIS * self::BILLING_WEEKS),
        );

        return [
            'plan' => $plan,
            'name' => $plan->label(),
            'priceCents' => $priceCents,
            'monthlyCredits' => $monthlyCredits,
            'weeklyAnalyses' => $weeklyAnalyses,
            'featured' => SubscriptionPlan::PRO === $plan,
        ];
    }
}
