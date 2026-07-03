<?php

declare(strict_types=1);

namespace App\DataFixtures\Integration;

use App\DataFixtures\Core\OrganizationFixtures;
use App\DataFixtures\Helper\FixtureReference;
use App\Entity\ApiKey;
use App\Entity\Organization;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Génère 8 clés API (2 par organisation principale).
 *
 * Dépend de :
 * - OrganizationFixtures
 */
final class ApiKeyFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['integration', 'demo'];
    }

    public function getDependencies(): array
    {
        return [OrganizationFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $orgRefs = [
            FixtureReference::ORG_AFRIDIL,
            FixtureReference::ORG_SKYMOTION,
            FixtureReference::ORG_WEBPULSE,
            FixtureReference::ORG_FREELANCE,
        ];

        $keyConfigs = [
            ['name' => 'API Production', 'scopes' => ['read', 'write'], 'revoked' => false, 'expired' => false],
            ['name' => 'API Staging', 'scopes' => ['read'], 'revoked' => false, 'expired' => false],
        ];

        $keyIndex = 0;
        foreach ($orgRefs as $orgIdx => $orgRef) {
            $org = $this->getReference($orgRef, Organization::class);

            foreach ($keyConfigs as $configIdx => $config) {
                $key = new ApiKey();
                $key->setName(sprintf('%s — %s', $config['name'], $org->getName()));
                $key->setOrganization($org);
                $key->setKeyHash(hash('sha256', sprintf('demo-api-key-%d-%d', $orgIdx, $configIdx)));
                $key->setScopesJson($config['scopes']);

                // Révoquer certaines clés (dernière org = freelance en difficulté)
                if (3 === $orgIdx && 1 === $configIdx) {
                    $key->setRevokedAt(new \DateTimeImmutable('-15 days'));
                }

                // Expiration future pour la plupart
                if (3 !== $orgIdx) {
                    $key->setExpiresAt(new \DateTimeImmutable('+6 months'));
                } else {
                    // Freelance : clé expirée
                    $key->setExpiresAt(new \DateTimeImmutable('-1 month'));
                }

                $manager->persist($key);
                ++$keyIndex;
            }
        }

        $manager->flush();
    }
}
