<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\Audit;
use App\Entity\Domain;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\AuditStatus;
use Doctrine\Persistence\ObjectManager;

final class AuditFactory
{
    public static function createCompleted(
        ObjectManager $manager,
        Project $project,
        Domain $domain,
        ?User $requestedBy,
        int $seoScore,
        int $cwvScore,
        int $pagesCrawled,
        \DateTimeImmutable $createdAt,
    ): Audit {
        $audit = new Audit();
        $audit
            ->setProject($project)
            ->setDomain($domain)
            ->setRequestedBy($requestedBy)
            ->setStatus(AuditStatus::COMPLETED)
            ->setSeoScore($seoScore)
            ->setCoreWebVitalsScore($cwvScore)
            ->setPagesCrawled($pagesCrawled)
            ->setPagesFailed(random_int(0, 2))
            ->setMaxPages(30)
            ->setMaxDepth(2)
            ->setCrawlStartedAt($createdAt->modify('+1 minute'))
            ->setCrawlFinishedAt($createdAt->modify(sprintf('+%d minutes', random_int(3, 15))))
            ->setCreatedAt($createdAt);

        $manager->persist($audit);

        return $audit;
    }

    public static function createFailed(
        ObjectManager $manager,
        Project $project,
        Domain $domain,
        ?User $requestedBy,
        string $errorMessage,
        \DateTimeImmutable $createdAt,
    ): Audit {
        $audit = new Audit();
        $audit
            ->setProject($project)
            ->setDomain($domain)
            ->setRequestedBy($requestedBy)
            ->setStatus(AuditStatus::FAILED)
            ->setErrorMessage($errorMessage)
            ->setMaxPages(30)
            ->setMaxDepth(2)
            ->setCrawlStartedAt($createdAt->modify('+1 minute'))
            ->setCreatedAt($createdAt);

        $manager->persist($audit);

        return $audit;
    }

    public static function createQueued(
        ObjectManager $manager,
        Project $project,
        Domain $domain,
        ?User $requestedBy,
    ): Audit {
        $audit = new Audit();
        $audit
            ->setProject($project)
            ->setDomain($domain)
            ->setRequestedBy($requestedBy)
            ->setStatus(AuditStatus::QUEUED)
            ->setMaxPages(30)
            ->setMaxDepth(2);

        $manager->persist($audit);

        return $audit;
    }

    public static function createRunning(
        ObjectManager $manager,
        Project $project,
        Domain $domain,
        ?User $requestedBy,
        \DateTimeImmutable $createdAt,
    ): Audit {
        $audit = new Audit();
        $audit
            ->setProject($project)
            ->setDomain($domain)
            ->setRequestedBy($requestedBy)
            ->setStatus(AuditStatus::RUNNING)
            ->setMaxPages(30)
            ->setMaxDepth(2)
            ->setPagesCrawled(random_int(3, 10))
            ->setCrawlStartedAt($createdAt->modify('+1 minute'))
            ->setCreatedAt($createdAt);

        $manager->persist($audit);

        return $audit;
    }
}
