<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\Audit;
use App\Entity\AuditPage;
use Doctrine\Persistence\ObjectManager;

final class AuditPageFactory
{
    public static function create(
        ObjectManager $manager,
        Audit $audit,
        string $url,
        int $statusCode = 200,
        ?string $title = null,
        ?string $metaDescription = null,
        ?string $h1 = null,
        ?int $wordCount = null,
        ?int $loadTimeMs = null,
    ): AuditPage {
        $page = new AuditPage();
        $page
            ->setAudit($audit)
            ->setUrl($url)
            ->setNormalizedUrl($url)
            ->setStatusCode($statusCode)
            ->setContentType('text/html')
            ->setTitle($title)
            ->setMetaDescription($metaDescription)
            ->setH1($h1)
            ->setWordCount($wordCount ?? random_int(200, 2500))
            ->setInternalLinksCount(random_int(3, 25))
            ->setExternalLinksCount(random_int(0, 8))
            ->setImagesWithoutAltCount(random_int(0, 5))
            ->setLoadTimeMs($loadTimeMs ?? random_int(200, 4000))
            ->setIsIndexable(200 === $statusCode)
            ->setStructuredDataPresent(random_int(0, 100) < 30);

        $manager->persist($page);

        return $page;
    }
}
