<?php

declare(strict_types=1);

namespace App\DataFixtures\Helper;

use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;

/**
 * Utilitaires partagés par toutes les fixtures.
 *
 * - Singleton Faker fr_FR
 * - Batch flush pour les fixtures volumineuses
 * - Profils de cohérence (SEO scores, issues, rankings)
 * - Listes de données réalistes hardcodées
 */
final class FixtureHelper
{
    private static ?Generator $faker = null;

    // ── Faker ──────────────────────────────────────────────────────────

    public static function faker(): Generator
    {
        return self::$faker ??= Factory::create('fr_FR');
    }

    // ── Batch flush ────────────────────────────────────────────────────

    /**
     * Flush périodique pour limiter la consommation mémoire.
     */
    public static function batchFlush(ObjectManager $manager, int $iteration, int $batchSize = FixtureConfig::BATCH_SIZE): void
    {
        if (($iteration + 1) % $batchSize === 0) {
            $manager->flush();
        }
    }

    // ── Profils de cohérence SEO ───────────────────────────────────────

    /** @var array<string, list<int>> */
    private const SEO_SCORE_PROFILES = [
        'growing'      => [42, 58, 74],
        'stable_good'  => [78, 82, 85],
        'struggling'   => [65, 55, 40],
        'new'          => [48],
    ];

    /**
     * Retourne un score SEO cohérent pour un profil et un index d'audit.
     */
    public static function seoScoreForProfile(string $profile, int $auditIndex): int
    {
        $scores = self::SEO_SCORE_PROFILES[$profile] ?? self::SEO_SCORE_PROFILES['stable_good'];
        $index = min($auditIndex, \count($scores) - 1);

        return $scores[$index] + random_int(-3, 3);
    }

    /**
     * Retourne un score Core Web Vitals proportionnel au score SEO.
     */
    public static function cwvScoreForSeoScore(int $seoScore): int
    {
        return max(20, min(100, $seoScore + random_int(-15, 5)));
    }

    /**
     * Retourne le nombre d'issues par sévérité, cohérent avec le score SEO.
     *
     * @return array{critical: int, high: int, medium: int, low: int, info: int}
     */
    public static function issueCountForScore(int $seoScore): array
    {
        if ($seoScore >= 80) {
            return ['critical' => 0, 'high' => random_int(1, 2), 'medium' => random_int(2, 5), 'low' => random_int(3, 6), 'info' => random_int(1, 3)];
        }

        if ($seoScore >= 50) {
            return ['critical' => random_int(1, 3), 'high' => random_int(3, 6), 'medium' => random_int(5, 10), 'low' => random_int(4, 8), 'info' => random_int(2, 5)];
        }

        return ['critical' => random_int(4, 8), 'high' => random_int(8, 15), 'medium' => random_int(10, 18), 'low' => random_int(5, 10), 'info' => random_int(3, 6)];
    }

    /**
     * Retourne une position de ranking cohérente pour un profil et un index de semaine.
     */
    public static function rankPositionForProfile(string $profile, int $weekIndex): int
    {
        return match ($profile) {
            'growing'     => max(1, 45 - ($weekIndex * random_int(5, 8)) + random_int(-2, 2)),
            'stable_good' => random_int(3, 15),
            'struggling'  => min(100, 15 + ($weekIndex * random_int(6, 10)) + random_int(-2, 2)),
            'new'         => random_int(30, 60),
            default       => random_int(10, 50),
        };
    }

    // ── Listes de données réalistes ────────────────────────────────────

    /** @return list<string> */
    public static function projectNames(): array
    {
        return [
            'E-Commerce Premium',
            'Blog Nutrition Santé',
            'Marketplace Mode',
            'Portail Immobilier',
            'Site Tourisme Provence',
            'Comparateur Assurances',
            'Plateforme E-learning',
            'Annuaire Artisans',
            'Magazine Tech',
            'Boutique Bio en ligne',
            'Site Cabinet Avocat',
            'Plateforme Coworking',
            'Guide Restaurants Lyon',
            'Agence Voyage Sur-Mesure',
            'Clinique Dentaire Paris',
            'Studio Yoga Bordeaux',
            'Garage Auto Marseille',
            'Librairie Indépendante',
            'Festival Musique Nantes',
            'Architecte Intérieur',
            'Photographe Mariage',
            'Coach Sportif Online',
        ];
    }

    /** @return list<string> */
    public static function projectDomains(): array
    {
        return [
            'premium-shop.fr',
            'nutri-sante-blog.com',
            'mode-marketplace.fr',
            'portail-immo.fr',
            'tourisme-provence.com',
            'comparateur-assurance.fr',
            'learn-online.fr',
            'annuaire-artisans.com',
            'magazine-tech.fr',
            'boutique-bio.com',
            'cabinet-avocat-paris.fr',
            'cowork-space.fr',
            'guide-restos-lyon.com',
            'voyage-surmesure.fr',
            'clinique-dentaire-paris.fr',
            'studio-yoga-bordeaux.fr',
            'garage-auto-marseille.fr',
            'librairie-independante.com',
            'festival-musique-nantes.fr',
            'archi-interieur.fr',
            'photo-mariage.fr',
            'coach-sportif-online.com',
        ];
    }

    /**
     * Mots-clés réalistes pour le projet Afridil.
     *
     * @return list<array{term: string, volume: int, difficulty: int, cpc: string, intent: string}>
     */
    public static function afridilKeywords(): array
    {
        return [
            ['term' => 'petites annonces côte d\'ivoire', 'volume' => 12000, 'difficulty' => 45, 'cpc' => '0.35', 'intent' => 'navigational'],
            ['term' => 'voiture occasion abidjan', 'volume' => 8500, 'difficulty' => 52, 'cpc' => '0.60', 'intent' => 'transactional'],
            ['term' => 'immobilier dakar', 'volume' => 6200, 'difficulty' => 58, 'cpc' => '1.20', 'intent' => 'transactional'],
            ['term' => 'emploi cameroun', 'volume' => 9800, 'difficulty' => 40, 'cpc' => '0.45', 'intent' => 'informational'],
            ['term' => 'annonces gratuites afrique', 'volume' => 15000, 'difficulty' => 35, 'cpc' => '0.25', 'intent' => 'navigational'],
            ['term' => 'location appartement abidjan', 'volume' => 4500, 'difficulty' => 48, 'cpc' => '0.80', 'intent' => 'transactional'],
            ['term' => 'vente terrain dakar', 'volume' => 3200, 'difficulty' => 55, 'cpc' => '1.50', 'intent' => 'transactional'],
            ['term' => 'offre emploi abidjan', 'volume' => 7200, 'difficulty' => 42, 'cpc' => '0.55', 'intent' => 'transactional'],
            ['term' => 'moto occasion lomé', 'volume' => 2800, 'difficulty' => 30, 'cpc' => '0.20', 'intent' => 'transactional'],
            ['term' => 'électroménager abidjan', 'volume' => 3500, 'difficulty' => 38, 'cpc' => '0.40', 'intent' => 'commercial'],
            ['term' => 'téléphone portable occasion', 'volume' => 11000, 'difficulty' => 50, 'cpc' => '0.70', 'intent' => 'commercial'],
            ['term' => 'site petites annonces', 'volume' => 5500, 'difficulty' => 60, 'cpc' => '0.90', 'intent' => 'navigational'],
            ['term' => 'meuble occasion abidjan', 'volume' => 2100, 'difficulty' => 28, 'cpc' => '0.15', 'intent' => 'transactional'],
            ['term' => 'maison à vendre abidjan', 'volume' => 4800, 'difficulty' => 62, 'cpc' => '2.00', 'intent' => 'transactional'],
            ['term' => 'service à domicile abidjan', 'volume' => 1800, 'difficulty' => 25, 'cpc' => '0.30', 'intent' => 'transactional'],
            ['term' => 'achat voiture neuve côte d\'ivoire', 'volume' => 3000, 'difficulty' => 55, 'cpc' => '1.80', 'intent' => 'transactional'],
            ['term' => 'annonce emploi sénégal', 'volume' => 6000, 'difficulty' => 38, 'cpc' => '0.50', 'intent' => 'transactional'],
            ['term' => 'vêtements occasion abidjan', 'volume' => 1500, 'difficulty' => 22, 'cpc' => '0.10', 'intent' => 'commercial'],
            ['term' => 'petit commerce à vendre', 'volume' => 2200, 'difficulty' => 45, 'cpc' => '1.00', 'intent' => 'transactional'],
            ['term' => 'petites annonces gratuites côte d\'ivoire', 'volume' => 9500, 'difficulty' => 40, 'cpc' => '0.30', 'intent' => 'navigational'],
            ['term' => 'appartement meublé abidjan', 'volume' => 3800, 'difficulty' => 50, 'cpc' => '0.95', 'intent' => 'transactional'],
            ['term' => 'ordinateur portable occasion abidjan', 'volume' => 2500, 'difficulty' => 35, 'cpc' => '0.45', 'intent' => 'commercial'],
            ['term' => 'camion occasion afrique', 'volume' => 1200, 'difficulty' => 42, 'cpc' => '0.75', 'intent' => 'transactional'],
            ['term' => 'déménagement abidjan', 'volume' => 1600, 'difficulty' => 30, 'cpc' => '0.60', 'intent' => 'transactional'],
            ['term' => 'garde enfant abidjan', 'volume' => 900, 'difficulty' => 18, 'cpc' => '0.20', 'intent' => 'transactional'],
            ['term' => 'cours particulier abidjan', 'volume' => 1100, 'difficulty' => 20, 'cpc' => '0.35', 'intent' => 'transactional'],
            ['term' => 'matériel informatique abidjan', 'volume' => 2000, 'difficulty' => 38, 'cpc' => '0.55', 'intent' => 'commercial'],
            ['term' => 'terrain à vendre cocody', 'volume' => 1800, 'difficulty' => 58, 'cpc' => '2.50', 'intent' => 'transactional'],
            ['term' => 'annonces automobiles afrique', 'volume' => 4200, 'difficulty' => 48, 'cpc' => '0.65', 'intent' => 'navigational'],
            ['term' => 'marketplace afrique', 'volume' => 7500, 'difficulty' => 55, 'cpc' => '0.85', 'intent' => 'navigational'],
        ];
    }

    /**
     * Mots-clés réalistes pour le projet SkyMotion.
     *
     * @return list<array{term: string, volume: int, difficulty: int, cpc: string, intent: string}>
     */
    public static function skymotionKeywords(): array
    {
        return [
            ['term' => 'location caméra RED', 'volume' => 2800, 'difficulty' => 42, 'cpc' => '3.50', 'intent' => 'transactional'],
            ['term' => 'matériel audiovisuel professionnel', 'volume' => 5200, 'difficulty' => 55, 'cpc' => '2.80', 'intent' => 'commercial'],
            ['term' => 'location steadicam paris', 'volume' => 1200, 'difficulty' => 30, 'cpc' => '2.20', 'intent' => 'transactional'],
            ['term' => 'location objectif cinéma', 'volume' => 1800, 'difficulty' => 35, 'cpc' => '2.50', 'intent' => 'transactional'],
            ['term' => 'location éclairage tournage', 'volume' => 2200, 'difficulty' => 38, 'cpc' => '1.80', 'intent' => 'transactional'],
            ['term' => 'location drone professionnel', 'volume' => 3500, 'difficulty' => 48, 'cpc' => '3.00', 'intent' => 'transactional'],
            ['term' => 'matériel son tournage', 'volume' => 1500, 'difficulty' => 32, 'cpc' => '1.50', 'intent' => 'commercial'],
            ['term' => 'location caméra Sony FX6', 'volume' => 900, 'difficulty' => 25, 'cpc' => '2.80', 'intent' => 'transactional'],
            ['term' => 'grip matériel cinéma', 'volume' => 800, 'difficulty' => 28, 'cpc' => '1.20', 'intent' => 'informational'],
            ['term' => 'location kit tournage complet', 'volume' => 1600, 'difficulty' => 40, 'cpc' => '4.00', 'intent' => 'transactional'],
            ['term' => 'comparatif caméra cinéma', 'volume' => 3200, 'difficulty' => 50, 'cpc' => '1.80', 'intent' => 'informational'],
            ['term' => 'location moniteur de retour', 'volume' => 600, 'difficulty' => 20, 'cpc' => '1.00', 'intent' => 'transactional'],
            ['term' => 'accessoire caméra RED Komodo', 'volume' => 1100, 'difficulty' => 30, 'cpc' => '2.00', 'intent' => 'commercial'],
            ['term' => 'location trépied vidéo professionnel', 'volume' => 700, 'difficulty' => 22, 'cpc' => '1.30', 'intent' => 'transactional'],
            ['term' => 'location matériel clip vidéo', 'volume' => 1400, 'difficulty' => 35, 'cpc' => '2.50', 'intent' => 'transactional'],
            ['term' => 'tarif location caméra cinéma', 'volume' => 2000, 'difficulty' => 45, 'cpc' => '3.20', 'intent' => 'commercial'],
            ['term' => 'location ARRI Alexa Mini', 'volume' => 1300, 'difficulty' => 38, 'cpc' => '4.50', 'intent' => 'transactional'],
            ['term' => 'matériel machinerie cinéma', 'volume' => 500, 'difficulty' => 25, 'cpc' => '1.00', 'intent' => 'informational'],
            ['term' => 'location slider motorisé', 'volume' => 400, 'difficulty' => 18, 'cpc' => '1.50', 'intent' => 'transactional'],
            ['term' => 'prestataire audiovisuel paris', 'volume' => 2800, 'difficulty' => 55, 'cpc' => '5.00', 'intent' => 'transactional'],
            ['term' => 'location prompteur vidéo', 'volume' => 600, 'difficulty' => 20, 'cpc' => '1.20', 'intent' => 'transactional'],
            ['term' => 'fond vert studio location', 'volume' => 1800, 'difficulty' => 32, 'cpc' => '2.00', 'intent' => 'transactional'],
            ['term' => 'location régie vidéo', 'volume' => 500, 'difficulty' => 22, 'cpc' => '1.80', 'intent' => 'transactional'],
            ['term' => 'location caméra Blackmagic', 'volume' => 1000, 'difficulty' => 28, 'cpc' => '2.20', 'intent' => 'transactional'],
            ['term' => 'location HMI éclairage cinéma', 'volume' => 400, 'difficulty' => 18, 'cpc' => '1.50', 'intent' => 'transactional'],
            ['term' => 'matériel vidéo événementiel', 'volume' => 2200, 'difficulty' => 42, 'cpc' => '2.80', 'intent' => 'commercial'],
            ['term' => 'location grue caméra paris', 'volume' => 350, 'difficulty' => 25, 'cpc' => '3.00', 'intent' => 'transactional'],
            ['term' => 'enregistreur externe vidéo', 'volume' => 900, 'difficulty' => 30, 'cpc' => '1.00', 'intent' => 'commercial'],
            ['term' => 'location caméra 4K professionnel', 'volume' => 2500, 'difficulty' => 45, 'cpc' => '3.50', 'intent' => 'transactional'],
            ['term' => 'location matériel streaming', 'volume' => 1800, 'difficulty' => 35, 'cpc' => '2.00', 'intent' => 'transactional'],
        ];
    }

    /**
     * Mots-clés SEO génériques pour les agences.
     *
     * @return list<array{term: string, volume: int, difficulty: int, cpc: string, intent: string}>
     */
    public static function genericSeoKeywords(): array
    {
        return [
            ['term' => 'audit seo gratuit', 'volume' => 14000, 'difficulty' => 65, 'cpc' => '4.50', 'intent' => 'transactional'],
            ['term' => 'référencement naturel', 'volume' => 22000, 'difficulty' => 75, 'cpc' => '6.00', 'intent' => 'informational'],
            ['term' => 'seo local', 'volume' => 8500, 'difficulty' => 50, 'cpc' => '3.80', 'intent' => 'informational'],
            ['term' => 'optimisation google', 'volume' => 12000, 'difficulty' => 60, 'cpc' => '5.20', 'intent' => 'informational'],
            ['term' => 'agence seo paris', 'volume' => 6500, 'difficulty' => 70, 'cpc' => '12.00', 'intent' => 'transactional'],
            ['term' => 'backlinks seo', 'volume' => 5200, 'difficulty' => 55, 'cpc' => '3.50', 'intent' => 'informational'],
            ['term' => 'mots clés longue traîne', 'volume' => 4800, 'difficulty' => 42, 'cpc' => '2.20', 'intent' => 'informational'],
            ['term' => 'core web vitals', 'volume' => 9200, 'difficulty' => 58, 'cpc' => '2.80', 'intent' => 'informational'],
            ['term' => 'geo marketing ia', 'volume' => 3200, 'difficulty' => 35, 'cpc' => '4.00', 'intent' => 'informational'],
            ['term' => 'llm optimization', 'volume' => 2800, 'difficulty' => 30, 'cpc' => '3.50', 'intent' => 'informational'],
            ['term' => 'ai search optimization', 'volume' => 4500, 'difficulty' => 40, 'cpc' => '5.00', 'intent' => 'informational'],
            ['term' => 'google ai overview seo', 'volume' => 6800, 'difficulty' => 45, 'cpc' => '3.80', 'intent' => 'informational'],
            ['term' => 'stratégie contenu seo', 'volume' => 5500, 'difficulty' => 48, 'cpc' => '4.20', 'intent' => 'informational'],
            ['term' => 'rédaction seo ia', 'volume' => 7200, 'difficulty' => 52, 'cpc' => '3.00', 'intent' => 'commercial'],
            ['term' => 'analyse concurrence seo', 'volume' => 3800, 'difficulty' => 45, 'cpc' => '4.80', 'intent' => 'commercial'],
            ['term' => 'suivi positionnement google', 'volume' => 4200, 'difficulty' => 50, 'cpc' => '5.50', 'intent' => 'commercial'],
            ['term' => 'netlinking stratégie', 'volume' => 2800, 'difficulty' => 55, 'cpc' => '6.00', 'intent' => 'informational'],
            ['term' => 'migration seo site web', 'volume' => 1800, 'difficulty' => 60, 'cpc' => '8.00', 'intent' => 'informational'],
            ['term' => 'schema markup seo', 'volume' => 3500, 'difficulty' => 40, 'cpc' => '2.50', 'intent' => 'informational'],
            ['term' => 'indexation google', 'volume' => 8000, 'difficulty' => 45, 'cpc' => '2.00', 'intent' => 'informational'],
        ];
    }

    /**
     * Types d'issues SEO avec messages et recommandations.
     *
     * @return list<array{type: string, severity: string, message: string, recommendation: string}>
     */
    public static function issueTypes(): array
    {
        return [
            ['type' => 'missing_meta_description', 'severity' => 'high', 'message' => 'La balise meta description est manquante.', 'recommendation' => 'Ajoutez une meta description unique de 150 à 160 caractères incluant le mot-clé principal.'],
            ['type' => 'duplicate_title', 'severity' => 'high', 'message' => 'Le titre de la page est dupliqué avec une autre page du site.', 'recommendation' => 'Créez un titre unique pour chaque page, incluant le mot-clé cible.'],
            ['type' => 'broken_link', 'severity' => 'critical', 'message' => 'Un lien interne mène vers une page en erreur 404.', 'recommendation' => 'Corrigez ou supprimez le lien cassé et mettez en place une redirection 301 si la page a été déplacée.'],
            ['type' => 'slow_page', 'severity' => 'medium', 'message' => 'Le temps de chargement de la page dépasse 3 secondes.', 'recommendation' => 'Optimisez les images, activez la mise en cache et minifiez les fichiers CSS/JS.'],
            ['type' => 'missing_alt', 'severity' => 'medium', 'message' => 'Des images n\'ont pas d\'attribut alt.', 'recommendation' => 'Ajoutez un attribut alt descriptif à chaque image pour l\'accessibilité et le SEO.'],
            ['type' => 'no_h1', 'severity' => 'high', 'message' => 'La page ne contient pas de balise H1.', 'recommendation' => 'Ajoutez une balise H1 unique contenant le mot-clé principal de la page.'],
            ['type' => 'thin_content', 'severity' => 'medium', 'message' => 'Le contenu de la page contient moins de 300 mots.', 'recommendation' => 'Enrichissez le contenu avec au moins 800 mots de texte pertinent et optimisé.'],
            ['type' => 'mixed_content', 'severity' => 'critical', 'message' => 'La page charge des ressources en HTTP sur une page HTTPS.', 'recommendation' => 'Migrez toutes les ressources vers HTTPS pour éviter les avertissements de sécurité.'],
            ['type' => 'redirect_chain', 'severity' => 'medium', 'message' => 'Une chaîne de redirections a été détectée (3+ sauts).', 'recommendation' => 'Simplifiez les redirections en une seule redirection 301 directe.'],
            ['type' => 'missing_canonical', 'severity' => 'low', 'message' => 'La balise canonical est absente de la page.', 'recommendation' => 'Ajoutez une balise canonical auto-référente pour éviter les problèmes de contenu dupliqué.'],
            ['type' => 'duplicate_content', 'severity' => 'high', 'message' => 'Le contenu de cette page est similaire à plus de 80% à une autre page.', 'recommendation' => 'Réécrivez le contenu pour le rendre unique ou utilisez une redirection 301.'],
            ['type' => 'large_image', 'severity' => 'low', 'message' => 'Une image dépasse 500 Ko sans compression.', 'recommendation' => 'Compressez l\'image au format WebP et utilisez le lazy loading.'],
            ['type' => 'missing_robots_txt', 'severity' => 'info', 'message' => 'Le fichier robots.txt est absent ou inaccessible.', 'recommendation' => 'Créez un fichier robots.txt à la racine du site pour guider les crawlers.'],
            ['type' => 'missing_sitemap', 'severity' => 'info', 'message' => 'Aucun sitemap XML n\'a été détecté.', 'recommendation' => 'Générez et soumettez un sitemap XML via Google Search Console.'],
            ['type' => 'multiple_h1', 'severity' => 'low', 'message' => 'Plusieurs balises H1 détectées sur la page.', 'recommendation' => 'Utilisez une seule balise H1 par page et structurez le contenu avec H2-H6.'],
            ['type' => 'unminified_css', 'severity' => 'low', 'message' => 'Les fichiers CSS ne sont pas minifiés.', 'recommendation' => 'Minifiez les fichiers CSS pour réduire le temps de chargement.'],
            ['type' => 'unminified_js', 'severity' => 'low', 'message' => 'Les fichiers JavaScript ne sont pas minifiés.', 'recommendation' => 'Minifiez et combinez les fichiers JS, utilisez le chargement différé (defer).'],
            ['type' => 'no_ssl', 'severity' => 'critical', 'message' => 'Le site n\'utilise pas HTTPS.', 'recommendation' => 'Installez un certificat SSL et redirigez tout le trafic HTTP vers HTTPS.'],
            ['type' => 'orphan_page', 'severity' => 'medium', 'message' => 'La page n\'est liée par aucune autre page du site.', 'recommendation' => 'Ajoutez des liens internes vers cette page depuis des pages pertinentes.'],
            ['type' => 'missing_structured_data', 'severity' => 'info', 'message' => 'Aucune donnée structurée (Schema.org) détectée.', 'recommendation' => 'Implémentez des données structurées adaptées au type de contenu (Article, Product, FAQ, etc.).'],
        ];
    }

    /**
     * URLs de pages réalistes pour les audits Afridil.
     *
     * @return list<string>
     */
    public static function afridilPagePaths(): array
    {
        return [
            '/', '/annonces', '/annonces/voitures', '/annonces/immobilier', '/annonces/emploi',
            '/annonce/voiture-occasion-abidjan-toyota-corolla', '/annonce/appartement-meuble-cocody',
            '/annonce/emploi-developpeur-web-dakar', '/annonce/moto-occasion-lome-honda',
            '/annonce/terrain-a-vendre-riviera', '/publier-annonce', '/contact', '/a-propos',
            '/annonces/electronique', '/annonces/services', '/annonces/mode',
            '/annonce/iphone-14-occasion-abidjan', '/annonce/demenagement-abidjan-express',
            '/blog/guide-annonces-gratuites', '/blog/top-10-voitures-occasion',
        ];
    }

    /**
     * URLs de pages réalistes pour les audits SkyMotion.
     *
     * @return list<string>
     */
    public static function skymotionPagePaths(): array
    {
        return [
            '/', '/location', '/location/cameras', '/location/objectifs', '/location/eclairage',
            '/location/camera-red-komodo', '/location/camera-sony-fx6', '/location/arri-alexa-mini',
            '/location/steadicam', '/location/drone-dji-inspire-3', '/tarifs', '/contact',
            '/a-propos', '/blog', '/blog/guide-camera-cinema',
            '/location/kit-tournage-complet', '/location/eclairage-hmi',
            '/location/fond-vert-studio', '/location/prompteur', '/realisations',
        ];
    }

    /**
     * URLs de pages génériques pour les audits de sites variés.
     *
     * @return list<string>
     */
    public static function genericPagePaths(): array
    {
        return [
            '/', '/a-propos', '/contact', '/services', '/blog',
            '/mentions-legales', '/politique-confidentialite', '/faq',
            '/blog/article-1', '/blog/article-2', '/blog/article-3',
            '/services/consulting', '/services/formation', '/equipe',
            '/temoignages', '/portfolio', '/tarifs',
        ];
    }

    /**
     * Titres d'articles réalistes pour le projet Afridil.
     *
     * @return list<string>
     */
    public static function afridilArticleTitles(): array
    {
        return [
            'Guide complet des annonces gratuites en Côte d\'Ivoire',
            'Top 10 des voitures d\'occasion les plus recherchées à Abidjan',
            'Comment vendre rapidement sur une marketplace africaine',
            'Immobilier à Dakar : tendances et prix du marché 2026',
            'Les meilleures pratiques pour publier une annonce efficace',
            'Comparatif des sites de petites annonces en Afrique de l\'Ouest',
            'Guide de l\'acheteur : éviter les arnaques en ligne',
            'Location d\'appartements meublés à Abidjan : guide complet',
            'Emploi au Cameroun : les secteurs qui recrutent en 2026',
            'Comment estimer le prix de son véhicule d\'occasion',
            'Les quartiers les plus prisés pour l\'immobilier à Abidjan',
            'Déménagement à Abidjan : tarifs et conseils pratiques',
            'Marketplace Afrique : l\'essor du commerce digital',
            'Guide des services à domicile à Abidjan',
            'Vente de terrain à Cocody : ce qu\'il faut savoir',
        ];
    }

    /**
     * Titres d'articles réalistes pour le projet SkyMotion.
     *
     * @return list<string>
     */
    public static function skymotionArticleTitles(): array
    {
        return [
            'Guide complet de la location de caméra cinéma en 2026',
            'Comparatif des objectifs cinéma : Canon CN-E vs Sigma Cine',
            'RED Komodo 6K : test et avis pour la production indépendante',
            'Comment choisir son éclairage pour un tournage professionnel',
            'Location de drone : réglementation et bonnes pratiques',
        ];
    }

    /**
     * Actions d'audit log réalistes.
     *
     * @return list<array{action: string, entityType: string}>
     */
    public static function auditLogActions(): array
    {
        return [
            ['action' => 'project.created', 'entityType' => 'Project'],
            ['action' => 'project.updated', 'entityType' => 'Project'],
            ['action' => 'audit.started', 'entityType' => 'Audit'],
            ['action' => 'audit.completed', 'entityType' => 'Audit'],
            ['action' => 'audit.failed', 'entityType' => 'Audit'],
            ['action' => 'article.created', 'entityType' => 'Article'],
            ['action' => 'article.published', 'entityType' => 'Article'],
            ['action' => 'user.login', 'entityType' => 'User'],
            ['action' => 'user.updated', 'entityType' => 'User'],
            ['action' => 'settings.updated', 'entityType' => 'Organization'],
            ['action' => 'subscription.created', 'entityType' => 'Subscription'],
            ['action' => 'payment.received', 'entityType' => 'Payment'],
            ['action' => 'keyword.added', 'entityType' => 'Keyword'],
            ['action' => 'backlink.proposed', 'entityType' => 'Backlink'],
            ['action' => 'domain.verified', 'entityType' => 'Domain'],
        ];
    }
}
