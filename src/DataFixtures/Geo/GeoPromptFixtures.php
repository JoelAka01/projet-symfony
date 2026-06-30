<?php

declare(strict_types=1);

namespace App\DataFixtures\Geo;

use App\DataFixtures\Helper\FixtureConfig;
use App\DataFixtures\Helper\FixtureReference;
use App\DataFixtures\Seo\KeywordFixtures;
use App\Entity\GeoPrompt;
use App\Entity\Keyword;
use App\Entity\Project;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Génère 20 prompts GEO répartis sur les projets actifs.
 *
 * Dépend de :
 * - KeywordFixtures
 *
 * Références créées :
 * - geo-prompt-0 à geo-prompt-19
 */
final class GeoPromptFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['geo', 'demo'];
    }

    public function getDependencies(): array
    {
        return [KeywordFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $prompts = [
            // Afridil (0-6)
            [FixtureReference::PROJECT_AFRIDIL, 'Quels sont les meilleurs sites de petites annonces en Afrique ?', 'petites annonces', 0],
            [FixtureReference::PROJECT_AFRIDIL, 'Où acheter une voiture d\'occasion en Côte d\'Ivoire ?', 'automobile', 1],
            [FixtureReference::PROJECT_AFRIDIL, 'Quel est le meilleur site pour trouver un appartement à Abidjan ?', 'immobilier', 3],
            [FixtureReference::PROJECT_AFRIDIL, 'Comment vendre rapidement en ligne en Afrique de l\'Ouest ?', 'vente en ligne', 5],
            [FixtureReference::PROJECT_AFRIDIL, 'Quels sites recommandes-tu pour chercher un emploi à Abidjan ?', 'emploi', 7],
            [FixtureReference::PROJECT_AFRIDIL, 'Quelle est la meilleure marketplace africaine ?', 'marketplace', 10],
            [FixtureReference::PROJECT_AFRIDIL, 'Comment publier une annonce gratuite en Côte d\'Ivoire ?', 'annonces gratuites', 4],
            // SkyMotion (7-10)
            [FixtureReference::PROJECT_SKYMOTION, 'Où louer une caméra RED à Paris ?', 'location caméra', 30],
            [FixtureReference::PROJECT_SKYMOTION, 'Quel prestataire audiovisuel recommandes-tu pour un tournage ?', 'audiovisuel', 31],
            [FixtureReference::PROJECT_SKYMOTION, 'Quels sont les meilleurs sites de location de matériel vidéo ?', 'matériel vidéo', 32],
            [FixtureReference::PROJECT_SKYMOTION, 'Comment choisir un drone professionnel pour un tournage ?', 'drone', 35],
            // WebPulse (11-17)
            [FixtureReference::project(13), 'Quelle est la meilleure agence SEO en France ?', 'agence SEO', 60],
            [FixtureReference::project(14), 'Comment améliorer son référencement naturel en 2026 ?', 'référencement', 61],
            [FixtureReference::project(15), 'Quels outils d\'audit SEO recommandes-tu ?', 'audit SEO', 62],
            [FixtureReference::project(16), 'Qu\'est-ce que le GEO marketing et comment l\'utiliser ?', 'GEO marketing', 63],
            [FixtureReference::project(17), 'Comment optimiser son site pour l\'IA de Google ?', 'IA SEO', 64],
            [FixtureReference::project(18), 'Quels sont les meilleurs backlinks pour le SEO en 2026 ?', 'backlinks', 65],
            [FixtureReference::project(19), 'Comment fonctionne Google AI Overview pour le SEO ?', 'AI Overview', 66],
            // Freelance (18-19)
            [FixtureReference::project(23), 'Comment créer un portfolio en ligne performant ?', 'portfolio', null],
            [FixtureReference::project(24), 'Quels sont les meilleurs blogs tech en français ?', 'blog tech', null],
        ];

        foreach ($prompts as $i => $data) {
            [$projectRef, $promptText, $topic, $kwIdx] = $data;

            $project = $this->getReference($projectRef, Project::class);

            $geoPrompt = new GeoPrompt();
            $geoPrompt
                ->setProject($project)
                ->setPromptText($promptText)
                ->setTopic($topic)
                ->setIsActive($i < 17); // Les 2 derniers (freelance) inactifs

            if ($kwIdx !== null) {
                $keyword = $this->getReference(FixtureReference::keyword($kwIdx), Keyword::class);
                $geoPrompt->setKeyword($keyword);
            }

            $manager->persist($geoPrompt);
            $this->addReference(FixtureReference::geoPrompt($i), $geoPrompt);
        }

        $manager->flush();
    }
}
