<?php

declare(strict_types=1);

namespace App\DataFixtures\Integration;

use App\DataFixtures\Content\ArticleFixtures;
use App\DataFixtures\Helper\FixtureReference;
use App\DataFixtures\Project\ProjectFixtures;
use App\Entity\Article;
use App\Entity\CmsConnection;
use App\Entity\CmsPublication;
use App\Entity\Project;
use App\Enum\ArticleStatus;
use App\Enum\CmsProvider;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Génère 5 connexions CMS et quelques publications liées.
 *
 * Dépend de :
 * - ProjectFixtures
 * - ArticleFixtures
 *
 * Références créées :
 * - cms-connection-0 à cms-connection-4
 *
 * Connexions :
 * - Afridil : WordPress (afridil.com)
 * - SkyMotion : WordPress (skymotionlocation.com)
 * - WebPulse : 2 WordPress clients + 1 Shopify
 */
final class CmsConnectionFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['integration', 'demo'];
    }

    public function getDependencies(): array
    {
        return [
            ProjectFixtures::class,
            ArticleFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $connections = [
            [
                'projectRef' => FixtureReference::PROJECT_AFRIDIL,
                'provider' => CmsProvider::WORDPRESS,
                'baseUrl' => 'https://afridil.com/wp-json',
                'active' => true,
            ],
            [
                'projectRef' => FixtureReference::PROJECT_SKYMOTION,
                'provider' => CmsProvider::WORDPRESS,
                'baseUrl' => 'https://skymotionlocation.com/wp-json',
                'active' => true,
            ],
            [
                'projectRef' => FixtureReference::project(13),
                'provider' => CmsProvider::WORDPRESS,
                'baseUrl' => 'https://premium-shop.fr/wp-json',
                'active' => true,
            ],
            [
                'projectRef' => FixtureReference::project(14),
                'provider' => CmsProvider::WORDPRESS,
                'baseUrl' => 'https://nutri-sante-blog.com/wp-json',
                'active' => true,
            ],
            [
                'projectRef' => FixtureReference::project(15),
                'provider' => CmsProvider::SHOPIFY,
                'baseUrl' => 'https://mode-marketplace.myshopify.com/admin/api',
                'active' => false,
            ],
        ];

        $cmsObjects = [];

        foreach ($connections as $i => $data) {
            $project = $this->getReference($data['projectRef'], Project::class);

            $cms = new CmsConnection();
            $cms
                ->setProject($project)
                ->setProvider($data['provider'])
                ->setBaseUrl($data['baseUrl'])
                ->setIsActive($data['active']);

            if ($data['active']) {
                $cms->setLastTestedAt(new \DateTimeImmutable(sprintf('-%d days', random_int(0, 7))));
            } else {
                $cms->setLastError('API key expired. Please reconnect.');
            }

            $manager->persist($cms);
            $cmsObjects[] = $cms;
            $this->addReference(FixtureReference::cmsConnection($i), $cms);
        }

        // Créer quelques CmsPublications pour les articles publiés Afridil
        $publishedArticleIndices = [0, 1, 2, 3, 4]; // Premiers articles Afridil (PUBLISHED)
        foreach ($publishedArticleIndices as $articleIdx) {
            $article = $this->getReference(FixtureReference::article($articleIdx), Article::class);

            if (ArticleStatus::PUBLISHED !== $article->getStatus()) {
                continue;
            }

            $publication = new CmsPublication();
            $publication
                ->setArticle($article)
                ->setCmsConnection($cmsObjects[0]) // WordPress Afridil
                ->setExternalPostId((string) random_int(100, 9999))
                ->setExternalUrl(sprintf('https://afridil.com/blog/%s', $article->getSlug() ?? 'article'))
                ->setStatus(ArticleStatus::PUBLISHED)
                ->setPublishedAt($article->getPublishedAt());

            $manager->persist($publication);
        }

        $manager->flush();
    }
}
