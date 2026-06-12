<?php

declare(strict_types=1);

namespace App\Tests\Unit\Billing;

use App\Enum\SubscriptionPlan;
use App\Service\Billing\PlanCatalog;
use PHPUnit\Framework\TestCase;

final class PlanCatalogTest extends TestCase
{
    public function testWeeklyLimitsAreCalculatedFromPriceAndEstimatedAnalysisCredits(): void
    {
        $catalog = new PlanCatalog();

        $starter = $catalog->get(SubscriptionPlan::STARTER);
        $pro = $catalog->get(SubscriptionPlan::PRO);
        $expert = $catalog->get(SubscriptionPlan::EXPERT);

        self::assertSame(900000, $starter['monthlyCredits']);
        self::assertSame(5, $starter['weeklyAnalyses']);
        self::assertSame(15, $pro['weeklyAnalyses']);
        self::assertSame(30, $expert['weeklyAnalyses']);
    }
}
