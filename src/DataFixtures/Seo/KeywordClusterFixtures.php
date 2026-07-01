<?php

declare(strict_types=1);

namespace App\DataFixtures\Seo;

use App\DataFixtures\Helper\FixtureReference;
use App\DataFixtures\Project\ProjectFixtures;
use App\Entity\KeywordCluster;
use App\Entity\Project;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Génère 15 clusters de mots-clés pour les projets phares.
 *
 * Dépend de :
 * - ProjectFixtures
 *
 * Références créées :
 * - keyword-cluster-0 à keyword-cluster-14
 */
final class KeywordClusterFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['seo', 'demo'];
    }

    public function getDependencies(): array
    {
        return [ProjectFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $clusterIndex = 0;

        // Afridil — 5 clusters
        $afridil = $this->getReference(FixtureReference::PROJECT_AFRIDIL, Project::class);
        $afridilClusters = [
            ['name' => 'Immobilier', 'intent' => 'transactional', 'topic' => 'Annonces immobilières en Afrique'],
            ['name' => 'Automobile', 'intent' => 'transactional', 'topic' => 'Vente de véhicules d\'occasion'],
            ['name' => 'Emploi', 'intent' => 'transactional', 'topic' => 'Offres d\'emploi et recrutement'],
            ['name' => 'Électronique', 'intent' => 'commercial', 'topic' => 'Appareils et gadgets d\'occasion'],
            ['name' => 'Services', 'intent' => 'transactional', 'topic' => 'Services à domicile et professionnels'],
        ];

        foreach ($afridilClusters as $data) {
            $cluster = $this->createCluster($manager, $afridil, $data['name'], $data['intent'], $data['topic']);
            $this->addReference(FixtureReference::keywordCluster($clusterIndex++), $cluster);
        }

        // SkyMotion — 5 clusters
        $skymotion = $this->getReference(FixtureReference::PROJECT_SKYMOTION, Project::class);
        $skymotionClusters = [
            ['name' => 'Caméras', 'intent' => 'transactional', 'topic' => 'Location de caméras cinéma'],
            ['name' => 'Objectifs', 'intent' => 'commercial', 'topic' => 'Objectifs cinéma et photo'],
            ['name' => 'Éclairage', 'intent' => 'transactional', 'topic' => 'Matériel d\'éclairage tournage'],
            ['name' => 'Son', 'intent' => 'transactional', 'topic' => 'Équipement audio professionnel'],
            ['name' => 'Machinerie', 'intent' => 'informational', 'topic' => 'Grip et machinerie cinéma'],
        ];

        foreach ($skymotionClusters as $data) {
            $cluster = $this->createCluster($manager, $skymotion, $data['name'], $data['intent'], $data['topic']);
            $this->addReference(FixtureReference::keywordCluster($clusterIndex++), $cluster);
        }

        // WebPulse — 5 clusters génériques SEO
        $webpulseProject = $this->getReference(FixtureReference::project(13), Project::class);
        $webpulseClusters = [
            ['name' => 'SEO Technique', 'intent' => 'informational', 'topic' => 'Audit et optimisation technique'],
            ['name' => 'Contenu SEO', 'intent' => 'informational', 'topic' => 'Stratégie de contenu et rédaction'],
            ['name' => 'Netlinking', 'intent' => 'informational', 'topic' => 'Stratégie de backlinks'],
            ['name' => 'SEO Local', 'intent' => 'transactional', 'topic' => 'Référencement local et Google Business'],
            ['name' => 'IA & SEO', 'intent' => 'informational', 'topic' => 'Impact de l\'IA sur le référencement'],
        ];

        foreach ($webpulseClusters as $data) {
            $cluster = $this->createCluster($manager, $webpulseProject, $data['name'], $data['intent'], $data['topic']);
            $this->addReference(FixtureReference::keywordCluster($clusterIndex++), $cluster);
        }

        $manager->flush();
    }

    private function createCluster(
        ObjectManager $manager,
        Project $project,
        string $name,
        string $intent,
        string $mainTopic,
    ): KeywordCluster {
        $cluster = new KeywordCluster();
        $cluster
            ->setProject($project)
            ->setName($name)
            ->setIntent($intent)
            ->setMainTopic($mainTopic);

        $manager->persist($cluster);

        return $cluster;
    }
}
