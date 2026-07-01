<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\Organization;
use Doctrine\Persistence\ObjectManager;

final class OrganizationFactory
{
    public static function create(
        ObjectManager $manager,
        string $name,
        ?string $billingEmail = null,
        bool $whiteLabelEnabled = false,
    ): Organization {
        $organization = new Organization();
        $organization
            ->setName($name)
            ->setBillingEmail($billingEmail)
            ->setWhiteLabelEnabled($whiteLabelEnabled);

        $manager->persist($organization);

        return $organization;
    }
}
