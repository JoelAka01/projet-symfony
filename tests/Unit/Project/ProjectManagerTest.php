<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project;

use App\Entity\Domain;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\UserRole;
use App\Exception\InvalidWebsiteUrlException;
use App\Service\Crawler\CrawlerUrlNormalizer;
use App\Service\Language\LanguageDetectionService;
use App\Service\Project\ProjectManager;
use App\Service\Project\ProjectWebsiteUrlNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ProjectManagerTest extends TestCase
{
    public function testItCreatesProjectWithPersonalOrganizationAndPrimaryWebsiteUrl(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::atLeastOnce())->method('persist');
        $entityManager->expects(self::once())->method('flush');

        $manager = new ProjectManager(
            $entityManager,
            new ProjectWebsiteUrlNormalizer(new CrawlerUrlNormalizer()),
            new LanguageDetectionService(
                $this->createMock(\Symfony\Contracts\HttpClient\HttpClientInterface::class),
                new NullLogger(),
            ),
            new NullLogger(),
        );

        $owner = $this->user();
        $project = new Project();
        $project->setName('Website audit');

        $manager->createForUser($project, $owner, 'example.com');

        self::assertSame($owner, $project->getOwner());
        self::assertNotNull($project->getOrganization());
        self::assertCount(1, $project->getDomains());
        self::assertCount(1, $owner->getOrganizationUsers());

        $domain = $project->getDomains()->first();
        self::assertInstanceOf(Domain::class, $domain);
        self::assertSame('https://example.com/', $domain->getRootDomain());
    }

    public function testItRejectsInvalidWebsiteTargets(): void
    {
        $manager = new ProjectManager(
            $this->createMock(EntityManagerInterface::class),
            new ProjectWebsiteUrlNormalizer(new CrawlerUrlNormalizer()),
            new LanguageDetectionService(
                $this->createMock(\Symfony\Contracts\HttpClient\HttpClientInterface::class),
                new NullLogger(),
            ),
            new NullLogger(),
        );

        $this->expectException(InvalidWebsiteUrlException::class);

        $manager->createForUser(new Project(), $this->user(), 'http://localhost/');
    }

    private function user(): User
    {
        $user = new User();
        $user
            ->setEmail('owner@example.com')
            ->setFirstName('Owner')
            ->setLastName('Demo')
            ->setRole(UserRole::VIEWER)
            ->setIsVerified(true)
            ->setPasswordHash('hash');

        return $user;
    }
}
