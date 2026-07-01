<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\Audit;
use App\Entity\AuditIssue;
use App\Entity\AuditPage;
use Doctrine\Persistence\ObjectManager;

final class AuditIssueFactory
{
    public static function create(
        ObjectManager $manager,
        Audit $audit,
        ?AuditPage $auditPage,
        string $issueType,
        string $severity,
        ?string $message = null,
        ?string $recommendation = null,
    ): AuditIssue {
        $issue = new AuditIssue();
        $issue
            ->setAudit($audit)
            ->setAuditPage($auditPage)
            ->setIssueType($issueType)
            ->setSeverity($severity)
            ->setMessage($message)
            ->setRecommendation($recommendation);

        $manager->persist($issue);

        return $issue;
    }
}
