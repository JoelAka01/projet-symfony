<?php

declare(strict_types=1);

namespace App\DataFixtures\Audit;

use App\DataFixtures\Factory\AuditFactory;
use App\DataFixtures\Helper\FixtureHelper;
use App\DataFixtures\Helper\FixtureReference;
use App\DataFixtures\Project\DomainFixtures;
use App\Entity\Domain;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Génère 50 audits avec progression temporelle selon les scénarios métier.
 *
 * Dépend de :
 * - DomainFixtures (et transitif : ProjectFixtures, OrganizationUserFixtures)
 *
 * Références créées :
 * - audit-0 à audit-49
 *
 * Scénarios temporels :
 * - Afridil : 3 audits/projet phare (janv→mars→mai), scores croissants
 * - SkyMotion : 1 audit récent par projet
 * - WebPulse : 2 audits/projet, mix stable
 * - Studio Freelance : 2 audits, scores déclinants
 */
final class AuditFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['audit', 'demo', 'test'];
    }

    public function getDependencies(): array
    {
        return [DomainFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $auditIndex = 0;

        // ── Org 0 : Afridil (projets 0-9, profil "growing") ───────────
        $auditIndex = $this->createAuditsForOrg($manager, 0, 10, 'growing', $auditIndex, FixtureReference::USER_ADMIN);

        // ── Org 1 : SkyMotion (projets 10-12, profil "new") ───────────
        $auditIndex = $this->createAuditsForOrg($manager, 10, 13, 'new', $auditIndex, FixtureReference::USER_MANAGER);

        // ── Org 2 : WebPulse (projets 13-22, profil "stable_good") ────
        $auditIndex = $this->createAuditsForOrg($manager, 13, 23, 'stable_good', $auditIndex, FixtureReference::user(8));

        // ── Org 3 : Studio Freelance (projets 23-24, profil "struggling")
        $auditIndex = $this->createAuditsForOrg($manager, 23, 25, 'struggling', $auditIndex, FixtureReference::USER_USER);

        $manager->flush();
    }

    private function createAuditsForOrg(
        ObjectManager $manager,
        int $projectStart,
        int $projectEnd,
        string $profile,
        int $auditIndex,
        string $userRef,
    ): int {
        $user = $this->getReference($userRef, User::class);

        $auditsPerProject = match ($profile) {
            'growing' => 3,
            'new' => 1,
            'struggling' => 2,
            default => 2,
        };

        $errorMessages = [
            'Connection timeout after 30 seconds',
            'DNS resolution failed for domain',
            'SSL certificate has expired',
            'Server returned HTTP 503 Service Unavailable',
            'Too many redirects (>10)',
        ];

        for ($p = $projectStart; $p < $projectEnd; ++$p) {
            $project = $this->getReference(FixtureReference::project($p), Project::class);
            $domain = $this->getReference(FixtureReference::domain($p), Domain::class);

            for ($a = 0; $a < $auditsPerProject; ++$a) {
                // Date de l'audit : espacement temporel réaliste
                $monthsAgo = match ($profile) {
                    'growing' => (($auditsPerProject - 1 - $a) * 2) + 1,     // 5, 3, 1 mois
                    'new' => 0,                                                // récent
                    'struggling' => (($auditsPerProject - 1 - $a) * 3) + 1,   // 4, 1 mois
                    default => (($auditsPerProject - 1 - $a) * 2) + 1,        // 3, 1 mois
                };
                $createdAt = new \DateTimeImmutable(sprintf('-%d months -%d days', $monthsAgo, random_int(0, 15)));

                // Injecter des statuts variés (~10% QUEUED, ~10% RUNNING, ~10% FAILED, ~70% COMPLETED)
                $rand = random_int(0, 100);
                if ($a === $auditsPerProject - 1 && $rand < 15 && 'new' !== $profile) {
                    // Dernier audit d'un projet : possibilité QUEUED ou RUNNING
                    if ($rand < 8) {
                        AuditFactory::createQueued($manager, $project, $domain, $user);
                        $this->addReference(FixtureReference::audit($auditIndex++), $project); // dummy ref for counting
                        continue;
                    }
                    $audit = AuditFactory::createRunning($manager, $project, $domain, $user, new \DateTimeImmutable('-1 hour'));
                    $this->addReference(FixtureReference::audit($auditIndex++), $audit);
                    continue;
                }

                if ('struggling' === $profile && $a === $auditsPerProject - 1 && $p === $projectEnd - 1) {
                    // Studio Freelance : dernier audit du dernier projet = FAILED
                    $audit = AuditFactory::createFailed(
                        $manager,
                        $project,
                        $domain,
                        $user,
                        $errorMessages[array_rand($errorMessages)],
                        $createdAt,
                    );
                    $this->addReference(FixtureReference::audit($auditIndex++), $audit);
                    continue;
                }

                // Audit COMPLETED avec score cohérent
                $seoScore = FixtureHelper::seoScoreForProfile($profile, $a);
                $cwvScore = FixtureHelper::cwvScoreForSeoScore($seoScore);
                $pagesCrawled = random_int(5, 20);

                $audit = AuditFactory::createCompleted(
                    $manager,
                    $project,
                    $domain,
                    $user,
                    $seoScore,
                    $cwvScore,
                    $pagesCrawled,
                    $createdAt,
                );
                $this->addReference(FixtureReference::audit($auditIndex++), $audit);
            }
        }

        return $auditIndex;
    }
}
