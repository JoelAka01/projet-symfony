<?php

declare(strict_types=1);

namespace App\Tests\Unit\InternalLinking;

use App\Entity\Project;
use App\Entity\SitePage;
use App\Enum\SitePageType;
use App\Repository\SitePageRepository;
use App\Service\InternalLinking\InternalLinkValidator;
use PHPUnit\Framework\TestCase;

final class InternalLinkValidatorTest extends TestCase
{
    public function testItFlagsUnknownInternalUrlsAndHeadingLinks(): void
    {
        $project = (new Project())->setName('Skymotion');
        $pages = [
            $this->page($project, '/contact/', SitePageType::CONTACT),
            $this->page($project, '/service/', SitePageType::SERVICE),
            $this->page($project, '/', SitePageType::HOME),
        ];
        $validator = new InternalLinkValidator($this->createMock(SitePageRepository::class));

        $result = $validator->validate(
            $project,
            '<h2><a href="/service/">Service</a></h2><p>Pour approfondir ce point, <a href="/contact/">Contact</a> <a href="/invented/">Invented</a></p><p><a href="/">en savoir plus</a> <a href="/service/">Service</a></p>',
            $pages,
        );

        self::assertFalse($result['is_valid']);
        self::assertSame(1, $result['heading_links']);
        self::assertGreaterThanOrEqual(1, $result['paragraphs_with_multiple_links']);
        self::assertContains('/invented/', $result['unknown_urls']);
        self::assertContains('en savoir plus', $result['generic_anchors']);
        self::assertContains('Pour approfondir ce point', $result['repetitive_phrases']);
    }

    private function page(Project $project, string $url, SitePageType $type): SitePage
    {
        return (new SitePage())
            ->setProject($project)
            ->setUrl($url)
            ->setTitle($type->label())
            ->setPageType($type)
            ->setBusinessPriority(80)
            ->setAnchorSuggestions([$type->label()]);
    }
}
