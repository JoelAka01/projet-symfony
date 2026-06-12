<?php

declare(strict_types=1);

namespace App\Tests\Unit\Report;

use App\Entity\Audit;
use App\Entity\Domain;
use App\Entity\Project;
use App\Service\Audit\AuditInsightsBuilder;
use App\Service\Report\AuditPdfGenerator;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class AuditPdfGeneratorTest extends TestCase
{
    public function testItGeneratesPdfBytesFromCrawlerAndClaudeData(): void
    {
        $project = (new Project())->setName('Example Business');
        $domain = (new Domain())->setRootDomain('https://example.com');
        $audit = (new Audit())
            ->setProject($project)
            ->setDomain($domain)
            ->setSeoScore(82)
            ->setPagesCrawled(4)
            ->setPagesFailed(0)
            ->setMetadata([
                'ai_analysis' => [
                    'status' => 'completed',
                    'summary' => 'The site has a sound base and needs stronger answer blocks.',
                    'global_score' => 79,
                    'content_score' => 72,
                    'geo_score' => 65,
                    'recommendations' => [],
                    'geo_analysis' => [
                        'methodology_notice' => 'Claude-only readiness estimate.',
                        'ai_brand_visibility' => [],
                        'ai_seo_optimizations' => [],
                    ],
                ],
            ]);

        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 3) . '/templates'));
        $generator = new AuditPdfGenerator($twig, new AuditInsightsBuilder());

        $pdf = $generator->generate($audit);

        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertGreaterThan(1000, strlen($pdf));
    }
}
