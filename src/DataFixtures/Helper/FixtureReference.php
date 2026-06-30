<?php

declare(strict_types=1);

namespace App\DataFixtures\Helper;

/**
 * Constantes et générateurs de noms de référence Doctrine Fixtures.
 *
 * Centralise tous les identifiants de référence pour éviter
 * les chaînes magiques et garantir la cohérence entre fixtures.
 */
final class FixtureReference
{
    // ── Utilisateurs démo ──────────────────────────────────────────────
    public const USER_ADMIN = 'user-admin';
    public const USER_MANAGER = 'user-manager';
    public const USER_USER = 'user-user';

    // ── Organisations ──────────────────────────────────────────────────
    public const ORG_AFRIDIL = 'org-afridil';
    public const ORG_SKYMOTION = 'org-skymotion';
    public const ORG_WEBPULSE = 'org-webpulse';
    public const ORG_FREELANCE = 'org-freelance';

    // ── Projets phares ─────────────────────────────────────────────────
    public const PROJECT_AFRIDIL = 'project-afridil';
    public const PROJECT_SKYMOTION = 'project-skymotion';

    // ── Domaines phares ────────────────────────────────────────────────
    public const DOMAIN_AFRIDIL = 'domain-afridil';
    public const DOMAIN_SKYMOTION = 'domain-skymotion';

    // ── Générateurs indexés ────────────────────────────────────────────

    public static function user(int $index): string
    {
        return sprintf('user-%d', $index);
    }

    public static function org(int $index): string
    {
        return sprintf('org-%d', $index);
    }

    public static function project(int $index): string
    {
        return sprintf('project-%d', $index);
    }

    public static function domain(int $index): string
    {
        return sprintf('domain-%d', $index);
    }

    public static function audit(int $index): string
    {
        return sprintf('audit-%d', $index);
    }

    public static function auditPage(int $index): string
    {
        return sprintf('audit-page-%d', $index);
    }

    public static function keyword(int $index): string
    {
        return sprintf('keyword-%d', $index);
    }

    public static function keywordCluster(int $index): string
    {
        return sprintf('keyword-cluster-%d', $index);
    }

    public static function article(int $index): string
    {
        return sprintf('article-%d', $index);
    }

    public static function report(int $index): string
    {
        return sprintf('report-%d', $index);
    }

    public static function subscription(int $index): string
    {
        return sprintf('subscription-%d', $index);
    }

    public static function backlink(int $index): string
    {
        return sprintf('backlink-%d', $index);
    }

    public static function geoPrompt(int $index): string
    {
        return sprintf('geo-prompt-%d', $index);
    }

    public static function cmsConnection(int $index): string
    {
        return sprintf('cms-connection-%d', $index);
    }
}
