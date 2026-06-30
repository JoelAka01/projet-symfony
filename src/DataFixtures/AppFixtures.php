<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\DataFixtures\Analytics\AiUsageFixtures;
use App\DataFixtures\Analytics\AnalyticsDailySnapshotFixtures;
use App\DataFixtures\Analytics\GeoDailySnapshotFixtures;
use App\DataFixtures\Audit\AuditFixtures;
use App\DataFixtures\Audit\AuditIssueFixtures;
use App\DataFixtures\Audit\AuditPageFixtures;
use App\DataFixtures\Backlink\BacklinkExchangeFixtures;
use App\DataFixtures\Backlink\BacklinkFixtures;
use App\DataFixtures\Backlink\BacklinkSiteFixtures;
use App\DataFixtures\Billing\PaymentFixtures;
use App\DataFixtures\Billing\SubscriptionFixtures;
use App\DataFixtures\Content\ArticleFixtures;
use App\DataFixtures\Content\ArticleImageFixtures;
use App\DataFixtures\Content\ReportFixtures;
use App\DataFixtures\Core\OrganizationFixtures;
use App\DataFixtures\Core\OrganizationUserFixtures;
use App\DataFixtures\Core\UserFixtures;
use App\DataFixtures\Geo\GeoPromptFixtures;
use App\DataFixtures\Geo\GeoResultFixtures;
use App\DataFixtures\Helper\FixtureConfig;
use App\DataFixtures\Integration\ApiKeyFixtures;
use App\DataFixtures\Integration\CmsConnectionFixtures;
use App\DataFixtures\Logs\AuditLogFixtures;
use App\DataFixtures\Project\DomainFixtures;
use App\DataFixtures\Project\ProjectFixtures;
use App\DataFixtures\Seo\KeywordClusterFixtures;
use App\DataFixtures\Seo\KeywordFixtures;
use App\DataFixtures\Seo\KeywordRankingFixtures;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Fixture finale — affiche un résumé après le chargement de toutes les fixtures.
 *
 * Exécutée en dernier grâce aux dépendances. Affiche :
 * - Le nombre d'entités créées (volumétrie configurée)
 * - Les identifiants de connexion demo
 */
final class AppFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['core', 'demo', 'test'];
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            OrganizationFixtures::class,
            OrganizationUserFixtures::class,
            ProjectFixtures::class,
            DomainFixtures::class,
            AuditFixtures::class,
            AuditPageFixtures::class,
            AuditIssueFixtures::class,
            KeywordClusterFixtures::class,
            KeywordFixtures::class,
            KeywordRankingFixtures::class,
            ArticleFixtures::class,
            ArticleImageFixtures::class,
            ReportFixtures::class,
            BacklinkFixtures::class,
            BacklinkExchangeFixtures::class,
            BacklinkSiteFixtures::class,
            SubscriptionFixtures::class,
            PaymentFixtures::class,
            ApiKeyFixtures::class,
            CmsConnectionFixtures::class,
            GeoPromptFixtures::class,
            GeoResultFixtures::class,
            AnalyticsDailySnapshotFixtures::class,
            GeoDailySnapshotFixtures::class,
            AiUsageFixtures::class,
            AuditLogFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        echo "\n";
        echo "========================================\n";
        echo "  SEO GEO AI — Fixtures loaded\n";
        echo "========================================\n";
        echo "\n";
        echo "  Entities created (configured volumes):\n";
        echo sprintf("    Users:                 %d\n", FixtureConfig::USERS);
        echo sprintf("    Organizations:         %d\n", FixtureConfig::ORGANIZATIONS);
        echo sprintf("    Projects:              %d\n", FixtureConfig::PROJECTS);
        echo sprintf("    Domains:               %d\n", FixtureConfig::DOMAINS);
        echo sprintf("    Audits:                %d\n", FixtureConfig::AUDITS);
        echo sprintf("    Audit pages:           ~%d\n", FixtureConfig::AUDIT_PAGES);
        echo sprintf("    Audit issues:          ~%d\n", FixtureConfig::AUDIT_ISSUES);
        echo sprintf("    Keyword clusters:      %d\n", FixtureConfig::KEYWORD_CLUSTERS);
        echo sprintf("    Keywords:              %d\n", FixtureConfig::KEYWORDS);
        echo sprintf("    Keyword rankings:      ~%d\n", FixtureConfig::KEYWORD_RANKINGS);
        echo sprintf("    Articles:              %d\n", FixtureConfig::ARTICLES);
        echo sprintf("    Article images:        ~%d\n", FixtureConfig::ARTICLE_IMAGES);
        echo sprintf("    Reports:               %d\n", FixtureConfig::REPORTS);
        echo sprintf("    Backlinks:             %d\n", FixtureConfig::BACKLINKS);
        echo sprintf("    Backlink exchanges:    %d\n", FixtureConfig::BACKLINK_EXCHANGES);
        echo sprintf("    Backlink sites:        %d\n", FixtureConfig::BACKLINK_SITES);
        echo sprintf("    Subscriptions:         %d\n", FixtureConfig::SUBSCRIPTIONS);
        echo sprintf("    Payments:              %d\n", FixtureConfig::PAYMENTS);
        echo sprintf("    API keys:              %d\n", FixtureConfig::API_KEYS);
        echo sprintf("    CMS connections:       %d\n", FixtureConfig::CMS_CONNECTIONS);
        echo sprintf("    GEO prompts:           %d\n", FixtureConfig::GEO_PROMPTS);
        echo sprintf("    GEO results:           %d\n", FixtureConfig::GEO_RESULTS);
        echo sprintf("    Analytics snapshots:   ~%d\n", FixtureConfig::ANALYTICS_DAILY_SNAPSHOTS);
        echo sprintf("    GEO snapshots:         ~%d\n", FixtureConfig::GEO_DAILY_SNAPSHOTS);
        echo sprintf("    AI usages:             %d\n", FixtureConfig::AI_USAGES);
        echo sprintf("    Audit log entries:     %d\n", FixtureConfig::AUDIT_LOG_ENTRIES);
        echo "\n";
        echo "  Demo credentials:\n";
        echo "    admin@example.com     / password  (ROLE_ADMIN)\n";
        echo "    manager@example.com   / password  (ROLE_MANAGER)\n";
        echo "    user@example.com      / password  (ROLE_USER)\n";
        echo "\n";
        echo "  Real projects:\n";
        echo "    afridil.com           (Petites annonces Afrique)\n";
        echo "    skymotionlocation.com (Location audiovisuel)\n";
        echo "========================================\n";
        echo "\n";
    }
}
