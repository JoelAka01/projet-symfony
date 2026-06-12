<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ai;

use App\Service\Ai\ClaudeSeoAnalysisSchema;
use PHPUnit\Framework\TestCase;

final class ClaudeSeoAnalysisSchemaTest extends TestCase
{
    public function testStructuredOutputSchemaRemainsCompact(): void
    {
        $schema = (new ClaudeSeoAnalysisSchema())->build();
        $json = json_encode($schema, JSON_THROW_ON_ERROR);

        self::assertLessThan(10000, strlen($json));
        self::assertSame(
            ['scores', 'summary', 'score_rationale', 'audience', 'strengths', 'weaknesses', 'blockers', 'quick_wins', 'keywords', 'technical', 'on_page', 'content_strategy', 'geo', 'recommendations', 'suggested_title', 'suggested_meta_description', 'serp_opportunities', 'day_30', 'day_60', 'day_90'],
            $schema['required'],
        );
    }
}
