<?php

declare(strict_types=1);

namespace App\DataFixtures\Content;

use App\DataFixtures\Helper\FixtureConfig;
use App\DataFixtures\Helper\FixtureHelper;
use App\DataFixtures\Helper\FixtureReference;
use App\DataFixtures\Project\ProjectFixtures;
use App\Entity\Project;
use App\Entity\Report;
use App\Enum\ReportStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Génère 20 rapports (héritage JOINED ContentItem → Report).
 *
 * Dépend de :
 * - ProjectFixtures
 *
 * Références créées :
 * - report-0 à report-19
 */
final class ReportFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['content', 'demo'];
    }

    public function getDependencies(): array
    {
        return [ProjectFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $faker = FixtureHelper::faker();
        $statuses = [
            ReportStatus::GENERATED, ReportStatus::GENERATED, ReportStatus::GENERATED,
            ReportStatus::GENERATED, ReportStatus::GENERATED, ReportStatus::GENERATED,
            ReportStatus::GENERATED, ReportStatus::GENERATED,
            ReportStatus::SENT, ReportStatus::SENT, ReportStatus::SENT,
            ReportStatus::SENT, ReportStatus::SENT,
            ReportStatus::QUEUED, ReportStatus::QUEUED, ReportStatus::QUEUED,
            ReportStatus::FAILED, ReportStatus::FAILED,
            ReportStatus::GENERATED, ReportStatus::SENT,
        ];

        $months = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin'];

        for ($i = 0; $i < FixtureConfig::REPORTS; $i++) {
            $projectIdx = $i % FixtureConfig::PROJECTS;
            $project = $this->getReference(FixtureReference::project($projectIdx), Project::class);
            $status = $statuses[$i];
            $monthName = $months[$i % \count($months)];

            $periodStart = new \DateTimeImmutable(sprintf('2026-%02d-01', ($i % 6) + 1));
            $periodEnd = $periodStart->modify('+1 month -1 day');

            $report = new Report();
            $report
                ->setProject($project)
                ->setTitle(sprintf('Rapport SEO — %s 2026', ucfirst($monthName)))
                ->setStatus($status)
                ->setPeriodStart($periodStart)
                ->setPeriodEnd($periodEnd);

            if (\in_array($status, [ReportStatus::GENERATED, ReportStatus::SENT], true)) {
                $report->setGeneratedAt(new \DateTimeImmutable(sprintf('-%d days', random_int(1, 30))));
                $report->setStorageUrl(sprintf('https://storage.seo-ai.test/reports/report-%d.pdf', $i));
            }

            if ($status === ReportStatus::SENT) {
                $report->setSentToEmail($faker->email());
                $report->setSentAt(new \DateTimeImmutable(sprintf('-%d days', random_int(1, 15))));
            }

            if ($status === ReportStatus::FAILED) {
                $report->setErrorMessage('Erreur lors de la génération du PDF : timeout du service DomPDF.');
            }

            $manager->persist($report);
            $this->addReference(FixtureReference::report($i), $report);
        }

        $manager->flush();
    }
}
