<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\ProjectStatus;
use Doctrine\Persistence\ObjectManager;

final class ProjectFactory
{
    public static function create(
        ObjectManager $manager,
        Organization $organization,
        User $owner,
        string $name,
        ProjectStatus $status = ProjectStatus::ACTIVE,
        ?string $language = 'fr',
        ?string $country = 'FR',
    ): Project {
        $project = new Project();
        $project
            ->setOwner($owner)
            ->setName($name)
            ->setStatus($status)
            ->setDefaultLanguage($language)
            ->setTargetCountry($country);

        $organization->addProject($project);

        $manager->persist($project);

        return $project;
    }
}
