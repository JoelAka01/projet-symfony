<?php

declare(strict_types=1);

namespace App\DataFixtures\Audit;

use App\DataFixtures\Factory\AuditPageFactory;
use App\DataFixtures\Helper\FixtureConfig;
use App\DataFixtures\Helper\FixtureHelper;
use App\DataFixtures\Helper\FixtureReference;
use App\Entity\Audit;
use App\Enum\AuditStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Génère ~250 pages d'audit pour les audits COMPLETED et RUNNING.
 *
 * Dépend de :
 * - AuditFixtures
 *
 * Références créées :
 * - audit-page-0 à audit-page-N
 */
final class AuditPageFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['audit', 'demo'];
    }

    public function getDependencies(): array
    {
        return [AuditFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $afridilPaths = FixtureHelper::afridilPagePaths();
        $skymotionPaths = FixtureHelper::skymotionPagePaths();
        $genericPaths = FixtureHelper::genericPagePaths();
        $faker = FixtureHelper::faker();
        $pageIndex = 0;

        for ($a = 0; $a < FixtureConfig::AUDITS; ++$a) {
            $ref = FixtureReference::audit($a);
            if (!$this->hasReference($ref, Audit::class)) {
                continue;
            }

            $audit = $this->getReference($ref, Audit::class);

            if (!\in_array($audit->getStatus(), [AuditStatus::COMPLETED, AuditStatus::RUNNING], true)) {
                continue;
            }

            // Déterminer les paths selon le projet
            $projectIndex = $this->guessProjectIndex($a);
            $paths = match (true) {
                $projectIndex <= 9 => $afridilPaths,
                $projectIndex <= 12 => $skymotionPaths,
                default => $genericPaths,
            };

            $domain = $audit->getDomain()?->getRootDomain() ?? 'example.com';
            $pageCount = $audit->getPagesCrawled() ?? random_int(5, 12);

            for ($p = 0; $p < $pageCount; ++$p) {
                $path = $paths[$p % \count($paths)];
                $url = sprintf('https://%s%s', $domain, $path);

                $statusCode = match (true) {
                    0 === $p => 200,                       // Page d'accueil toujours 200
                    random_int(0, 100) < 5 => 404,         // 5% de 404
                    random_int(0, 100) < 8 => 301,         // 8% de redirections
                    default => 200,
                };

                $page = AuditPageFactory::create(
                    $manager,
                    $audit,
                    $url,
                    $statusCode,
                    200 === $statusCode ? $faker->sentence(6) : null,
                    200 === $statusCode ? $faker->sentence(15) : null,
                    200 === $statusCode ? $faker->sentence(5) : null,
                    200 === $statusCode ? random_int(200, 2500) : null,
                    random_int(150, 4500),
                );

                $this->addReference(FixtureReference::auditPage($pageIndex), $page);
                ++$pageIndex;

                FixtureHelper::batchFlush($manager, $pageIndex);
            }
        }

        $manager->flush();
    }

    /**
     * Estimation de l'index projet à partir de l'index audit.
     */
    private function guessProjectIndex(int $auditIndex): int
    {
        // Afridil: projets 0-9, 3 audits/projet = indices 0-29
        if ($auditIndex < 30) {
            return (int) ($auditIndex / 3);
        }
        // SkyMotion: projets 10-12, 1 audit/projet = indices 30-32
        if ($auditIndex < 33) {
            return 10 + ($auditIndex - 30);
        }
        // WebPulse: projets 13-22, 2 audits/projet = indices 33-52
        if ($auditIndex < 53) {
            return 13 + (int) (($auditIndex - 33) / 2);
        }

        // Studio Freelance: projets 23-24
        return 23 + (int) (($auditIndex - 53) / 2);
    }
}
