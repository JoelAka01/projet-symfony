<?php

declare(strict_types=1);

namespace App\DataFixtures\Helper;

/**
 * Constantes centralisées de volumétrie pour les fixtures.
 *
 * Pour modifier la quantité de données générées,
 * il suffit de changer une seule valeur ici.
 */
final class FixtureConfig
{
    public const USERS = 15;
    public const DEMO_USERS = 3;
    public const ORGANIZATIONS = 4;
    public const PROJECTS = 25;
    public const DOMAINS = 25;
    public const AUDITS = 50;
    public const AUDIT_PAGES = 250;
    public const AUDIT_ISSUES = 1000;
    public const KEYWORD_CLUSTERS = 15;
    public const KEYWORDS = 200;
    public const KEYWORD_RANKINGS = 1000;
    public const ARTICLES = 50;
    public const ARTICLE_IMAGES = 80;
    public const REPORTS = 20;
    public const BACKLINKS = 30;
    public const BACKLINK_EXCHANGES = 10;
    public const BACKLINK_SITES = 10;
    public const SUBSCRIPTIONS = 20;
    public const PAYMENTS = 50;
    public const API_KEYS = 8;
    public const CMS_CONNECTIONS = 5;
    public const GEO_PROMPTS = 20;
    public const GEO_RESULTS = 60;
    public const GEO_DAILY_SNAPSHOTS = 30;
    public const ANALYTICS_DAILY_SNAPSHOTS = 30;
    public const AI_USAGES = 20;
    public const AUDIT_LOG_ENTRIES = 100;

    /** Taille de lot pour flush périodique sur les fixtures volumineuses. */
    public const BATCH_SIZE = 100;
}
