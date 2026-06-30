<?php

declare(strict_types=1);

namespace App\DataFixtures\Content;

use App\DataFixtures\Helper\FixtureConfig;
use App\DataFixtures\Helper\FixtureHelper;
use App\DataFixtures\Helper\FixtureReference;
use App\Entity\Article;
use App\Entity\ArticleImage;
use App\Enum\ArticleStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Génère ~80 images liées aux articles publiés/générés.
 *
 * Dépend de :
 * - ArticleFixtures
 */
final class ArticleImageFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['content', 'demo'];
    }

    public function getDependencies(): array
    {
        return [ArticleFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $faker = FixtureHelper::faker();
        $providers = ['dall-e', 'midjourney', null];
        $dimensions = [
            ['width' => 800, 'height' => 600],
            ['width' => 1200, 'height' => 630],
            ['width' => 1920, 'height' => 1080],
        ];

        $imageCount = 0;

        for ($a = 0; $a < FixtureConfig::ARTICLES; $a++) {
            $article = $this->getReference(FixtureReference::article($a), Article::class);

            if (!\in_array($article->getStatus(), [ArticleStatus::PUBLISHED, ArticleStatus::GENERATED], true)) {
                continue;
            }

            // 1-2 images par article publié/généré
            $nbImages = random_int(1, 2);

            for ($i = 0; $i < $nbImages; $i++) {
                $dim = $dimensions[array_rand($dimensions)];

                $image = new ArticleImage();
                $image
                    ->setArticle($article)
                    ->setStorageUrl(sprintf('https://picsum.photos/seed/article%d-img%d/%d/%d', $a, $i, $dim['width'], $dim['height']))
                    ->setAltText($faker->sentence(5))
                    ->setProvider($providers[array_rand($providers)])
                    ->setWidth($dim['width'])
                    ->setHeight($dim['height']);

                $manager->persist($image);
                $imageCount++;
            }
        }

        $manager->flush();
    }
}
