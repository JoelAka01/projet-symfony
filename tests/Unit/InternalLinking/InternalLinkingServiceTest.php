<?php

declare(strict_types=1);

namespace App\Tests\Unit\InternalLinking;

use App\Entity\Article;
use App\Entity\Project;
use App\Entity\SitePage;
use App\Enum\SitePageType;
use App\Repository\InternalLinkSuggestionRepository;
use App\Repository\SitePageRepository;
use App\Service\InternalLinking\InternalLinkValidator;
use App\Service\InternalLinking\InternalLinkingService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class InternalLinkingServiceTest extends TestCase
{
    public function testItInsertsRelevantInternalLinksWithoutLinkingHeadings(): void
    {
        $project = (new Project())->setName('Skymotion');
        $article = (new Article())
            ->setProject($project)
            ->setTitle('Location camera')
            ->setContentHtml('<h2>location camera professionnelle</h2><p>La location camera professionnelle aide les equipes evenementielles.</p><p>La captation video evenement structure le rendu.</p><p>Vous pouvez demander un devis pour avancer.</p>');
        $pages = [
            $this->page($project, '/', 'Skymotion', SitePageType::HOME, ['page accueil']),
            $this->page($project, '/location-camera-professionnelle/', 'Location camera', SitePageType::SERVICE, ['location camera professionnelle']),
            $this->page($project, '/captation-video-evenement/', 'Captation video', SitePageType::SERVICE, ['captation video evenement']),
            $this->page($project, '/contact/', 'Contact', SitePageType::CONTACT, ['demander un devis']),
        ];

        $service = $this->service($pages);
        $summary = $service->apply($article, $project, (string) $article->getContentHtml());
        $content = (string) $article->getContentHtml();

        self::assertGreaterThanOrEqual(3, $summary['validation']['unique_internal_urls']);
        self::assertStringContainsString('<a href="/location-camera-professionnelle/">location camera professionnelle</a>', $content);
        self::assertStringContainsString('<a href="/captation-video-evenement/">captation video evenement</a>', $content);
        self::assertStringContainsString('<a href="/contact/">demander un devis</a>', $content);
        self::assertStringContainsString('<h2>location camera professionnelle</h2>', $content);
    }

    public function testItDoesNotDuplicateLinksOnRetry(): void
    {
        $project = (new Project())->setName('Skymotion');
        $article = (new Article())
            ->setProject($project)
            ->setTitle('Location camera')
            ->setContentHtml('<p>La location camera professionnelle aide les equipes.</p><p>Vous pouvez demander un devis pour avancer.</p><p>La captation video evenement structure le rendu.</p>');
        $pages = [
            $this->page($project, '/location-camera-professionnelle/', 'Location camera', SitePageType::SERVICE, ['location camera professionnelle']),
            $this->page($project, '/captation-video-evenement/', 'Captation video', SitePageType::SERVICE, ['captation video evenement']),
            $this->page($project, '/contact/', 'Contact', SitePageType::CONTACT, ['demander un devis']),
        ];

        $service = $this->service($pages);
        $service->apply($article, $project, (string) $article->getContentHtml());
        $once = substr_count((string) $article->getContentHtml(), '<a href=');

        $service->apply($article, $project, (string) $article->getContentHtml());

        self::assertSame($once, substr_count((string) $article->getContentHtml(), '<a href='));
        self::assertSame(3, $once);
    }

    public function testItDoesNotAddArtificialFallbackSentencesWhenNoContextMatches(): void
    {
        $project = (new Project())->setName('Skymotion');
        $article = (new Article())
            ->setProject($project)
            ->setTitle('Location camera')
            ->setContentHtml('<p>Les equipes techniques preparent le tournage avec une regie adaptee.</p>');
        $pages = [
            $this->page($project, '/materiel-son-professionnel/', 'Materiel son', SitePageType::PRODUCT, ['materiel son professionnel']),
        ];

        $service = $this->service($pages);
        $summary = $service->apply($article, $project, (string) $article->getContentHtml());

        self::assertSame([], $summary['inserted_links']);
        self::assertStringNotContainsString('Pour approfondir ce point', (string) $article->getContentHtml());
        self::assertStringNotContainsString('<a href=', (string) $article->getContentHtml());
    }

    public function testItRejectsGenericAnchors(): void
    {
        $project = (new Project())->setName('Skymotion');
        $article = (new Article())
            ->setProject($project)
            ->setTitle('Location camera')
            ->setContentHtml('<p>Ce produit peut etre integre dans une production evenementielle.</p>');
        $pages = [
            $this->page($project, '/produit/', 'Produit', SitePageType::PRODUCT, ['ce produit']),
        ];

        $service = $this->service($pages);
        $summary = $service->apply($article, $project, (string) $article->getContentHtml());

        self::assertSame([], $summary['inserted_links']);
        self::assertStringNotContainsString('<a href="/produit/">ce produit</a>', (string) $article->getContentHtml());
    }

    public function testItLimitsInternalLinksToOnePerParagraph(): void
    {
        $project = (new Project())->setName('Skymotion');
        $article = (new Article())
            ->setProject($project)
            ->setTitle('Location camera')
            ->setContentHtml('<p>La location camera professionnelle et la captation video evenement structurent le dispositif.</p>');
        $pages = [
            $this->page($project, '/location-camera-professionnelle/', 'Location camera', SitePageType::SERVICE, ['location camera professionnelle']),
            $this->page($project, '/captation-video-evenement/', 'Captation video', SitePageType::SERVICE, ['captation video evenement']),
        ];

        $service = $this->service($pages);
        $service->apply($article, $project, (string) $article->getContentHtml());

        self::assertSame(1, substr_count((string) $article->getContentHtml(), '<a href='));
    }

    public function testItCleansRepetitiveGeneratedSentencesBeforeReapplying(): void
    {
        $project = (new Project())->setName('Skymotion');
        $article = (new Article())
            ->setProject($project)
            ->setTitle('Location camera')
            ->setContentHtml('<p>Pour approfondir ce point, consultez <a href="/produit/">ce produit</a>.</p><p>La location camera professionnelle aide les equipes.</p>');
        $pages = [
            $this->page($project, '/location-camera-professionnelle/', 'Location camera', SitePageType::SERVICE, ['location camera professionnelle']),
        ];

        $service = $this->service($pages);
        $service->apply($article, $project, (string) $article->getContentHtml());

        self::assertStringNotContainsString('Pour approfondir ce point', (string) $article->getContentHtml());
        self::assertStringNotContainsString('ce produit', (string) $article->getContentHtml());
        self::assertStringContainsString('<a href="/location-camera-professionnelle/">location camera professionnelle</a>', (string) $article->getContentHtml());
    }

    /** @param list<SitePage> $pages */
    private function service(array $pages): InternalLinkingService
    {
        $sitePageRepository = $this->createMock(SitePageRepository::class);
        $sitePageRepository
            ->method('findActiveForProject')
            ->willReturn($pages);

        $suggestionRepository = $this->createMock(InternalLinkSuggestionRepository::class);
        $suggestionRepository
            ->method('findForArticle')
            ->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $validator = new InternalLinkValidator($sitePageRepository);

        return new InternalLinkingService($sitePageRepository, $suggestionRepository, $validator, $entityManager, new NullLogger());
    }

    /** @param list<string> $anchors */
    private function page(Project $project, string $url, string $title, SitePageType $type, array $anchors): SitePage
    {
        return (new SitePage())
            ->setProject($project)
            ->setUrl($url)
            ->setTitle($title)
            ->setPageType($type)
            ->setTargetKeyword($anchors[0] ?? $title)
            ->setBusinessPriority(80)
            ->setAnchorSuggestions($anchors);
    }
}
