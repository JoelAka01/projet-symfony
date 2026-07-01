<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\Keyword;
use App\Entity\KeywordCluster;
use App\Entity\Project;
use Doctrine\Persistence\ObjectManager;

final class KeywordFactory
{
    public static function create(
        ObjectManager $manager,
        Project $project,
        string $term,
        ?int $searchVolume = null,
        ?int $difficulty = null,
        ?string $cpc = null,
        ?string $intent = null,
        ?KeywordCluster $cluster = null,
        ?string $source = null,
    ): Keyword {
        $keyword = new Keyword();
        $keyword
            ->setProject($project)
            ->setTerm($term)
            ->setSearchVolume($searchVolume)
            ->setDifficulty($difficulty)
            ->setCpc($cpc)
            ->setIntent($intent)
            ->setKeywordCluster($cluster)
            ->setSource($source ?? 'fixture');

        $manager->persist($keyword);

        return $keyword;
    }
}
