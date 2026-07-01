<?php

declare(strict_types=1);

namespace App\DataFixtures\Audit;

use App\DataFixtures\Factory\AuditIssueFactory;
use App\DataFixtures\Helper\FixtureConfig;
use App\DataFixtures\Helper\FixtureHelper;
use App\DataFixtures\Helper\FixtureReference;
use App\Entity\Audit;
use App\Entity\AuditPage;
use App\Enum\AuditStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Génère ~1000 issues SEO cohérentes avec les scores d'audit.
 *
 * Dépend de :
 * - AuditFixtures
 * - AuditPageFixtures
 *
 * La distribution des sévérités est déterminée par le score SEO :
 * - Score 80+ : peu d'issues (0 critical, 1-2 high)
 * - Score 50-80 : issues modérées (1-3 critical, 3-6 high)
 * - Score <50 : beaucoup d'issues (4-8 critical, 8-15 high)
 */
final class AuditIssueFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['audit', 'demo'];
    }

    public function getDependencies(): array
    {
        return [
            AuditFixtures::class,
            AuditPageFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $issueTypes = FixtureHelper::issueTypes();
        $issueIndex = 0;
        $pageRefIndex = 0;

        for ($a = 0; $a < FixtureConfig::AUDITS; $a++) {
            $ref = FixtureReference::audit($a);
            if (!$this->hasReference($ref, Audit::class)) {
                continue;
            }

            $audit = $this->getReference($ref, Audit::class);

            if ($audit->getStatus() !== AuditStatus::COMPLETED) {
                // Avancer le pageRefIndex pour les audits RUNNING aussi
                $pagesCrawled = $audit->getPagesCrawled() ?? 0;
                $pageRefIndex += $pagesCrawled;
                continue;
            }

            $seoScore = $audit->getSeoScore() ?? 60;
            $issueCounts = FixtureHelper::issueCountForScore($seoScore);
            $pagesCrawled = $audit->getPagesCrawled() ?? 5;

            // Collecter les pages de cet audit
            $auditPages = [];
            for ($p = 0; $p < $pagesCrawled; $p++) {
                $pageRef = FixtureReference::auditPage($pageRefIndex + $p);
                if ($this->hasReference($pageRef, AuditPage::class)) {
                    $auditPages[] = $this->getReference($pageRef, AuditPage::class);
                }
            }
            $pageRefIndex += $pagesCrawled;

            // Générer les issues par sévérité
            foreach ($issueCounts as $severity => $count) {
                for ($i = 0; $i < $count; $i++) {
                    $issueType = $issueTypes[array_rand($issueTypes)];

                    // 80% des issues liées à une page, 20% audit-level
                    $auditPage = null;
                    if (!empty($auditPages) && random_int(0, 100) < 80) {
                        $auditPage = $auditPages[array_rand($auditPages)];
                    }

                    // Utiliser la sévérité du bucket, pas celle du type prédéfini
                    AuditIssueFactory::create(
                        $manager,
                        $audit,
                        $auditPage,
                        $issueType['type'],
                        $severity,
                        $issueType['message'],
                        $issueType['recommendation'],
                    );

                    $issueIndex++;
                    FixtureHelper::batchFlush($manager, $issueIndex);
                }
            }
        }

        $manager->flush();
    }
}
