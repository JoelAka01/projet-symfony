<?php

declare(strict_types=1);

namespace App\DataFixtures\Core;

use App\DataFixtures\Factory\OrganizationFactory;
use App\DataFixtures\Helper\FixtureReference;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Génère les organisations de démonstration.
 *
 * Dépend de : rien
 *
 * Références créées :
 * - org-afridil, org-skymotion, org-webpulse, org-freelance
 * - org-0 à org-3
 */
final class OrganizationFixtures extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['core', 'demo', 'test'];
    }

    public function load(ObjectManager $manager): void
    {
        /** @var list<array{name: string, email: string, whiteLabel: bool, ref: string}> $orgs */
        $orgs = [
            ['name' => 'Afridil Digital', 'email' => 'contact@afridil.com', 'whiteLabel' => false, 'ref' => FixtureReference::ORG_AFRIDIL],
            ['name' => 'SkyMotion Prod', 'email' => 'contact@skymotionlocation.com', 'whiteLabel' => false, 'ref' => FixtureReference::ORG_SKYMOTION],
            ['name' => 'WebPulse Agency', 'email' => 'admin@webpulse-agency.fr', 'whiteLabel' => true, 'ref' => FixtureReference::ORG_WEBPULSE],
            ['name' => 'Studio Freelance', 'email' => 'user@seo-ai.test', 'whiteLabel' => false, 'ref' => FixtureReference::ORG_FREELANCE],
        ];

        foreach ($orgs as $index => $data) {
            $org = OrganizationFactory::create($manager, $data['name'], $data['email'], $data['whiteLabel']);
            $this->addReference($data['ref'], $org);
            $this->addReference(FixtureReference::org($index), $org);
        }

        $manager->flush();
    }
}
