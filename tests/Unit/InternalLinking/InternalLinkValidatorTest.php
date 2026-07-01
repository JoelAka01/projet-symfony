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
            '<h2><a href="/service/">Service</a></h2><p><a href="/contact/">Contact</a> <a href="/invented/">Invented</a></p>',
            $pages,
        );

        self::assertFalse($result['is_valid']);
        self::assertSame(1, $result['heading_links']);
        self::assertContains('/invented/', $result['unknown_urls']);
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
