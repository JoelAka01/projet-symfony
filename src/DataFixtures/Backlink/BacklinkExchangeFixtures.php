<?php

declare(strict_types=1);

namespace App\DataFixtures\Backlink;

use App\DataFixtures\Helper\FixtureConfig;
use App\DataFixtures\Helper\FixtureHelper;
use App\DataFixtures\Helper\FixtureReference;
use App\Entity\Backlink;
use App\Entity\BacklinkExchange;
use App\Entity\Project;
use App\Enum\BacklinkStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Génère 10 échanges de backlinks entre projets.
 *
 * Dépend de :
 * - BacklinkFixtures
 */
final class BacklinkExchangeFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['backlink', 'demo'];
    }

    public function getDependencies(): array
    {
        return [BacklinkFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $faker = FixtureHelper::faker();
        $projectDomains = FixtureHelper::projectDomains();

        $statuses = [
            BacklinkStatus::PROPOSED, BacklinkStatus::PROPOSED,
            BacklinkStatus::ACCEPTED, BacklinkStatus::ACCEPTED, BacklinkStatus::ACCEPTED,
            BacklinkStatus::PLACED, BacklinkStatus::PLACED,
            BacklinkStatus::REJECTED, BacklinkStatus::REJECTED,
            BacklinkStatus::BROKEN,
        ];

        for ($i = 0; $i < FixtureConfig::BACKLINK_EXCHANGES; ++$i) {
            $requesterIdx = $i % FixtureConfig::PROJECTS;
            $publisherIdx = ($requesterIdx + random_int(2, 10)) % FixtureConfig::PROJECTS;

            $requesterProject = $this->getReference(FixtureReference::project($requesterIdx), Project::class);
            $publisherProject = $this->getReference(FixtureReference::project($publisherIdx), Project::class);

            $exchange = new BacklinkExchange();
            $exchange
                ->setRequesterProject($requesterProject)
                ->setPublisherProject($publisherProject)
                ->setStatus($statuses[$i])
                ->setMatchScore(random_int(40, 95))
                ->setRequestedAnchorText($faker->words(3, true))
                ->setRequestedTargetUrl(sprintf('https://%s/', $projectDomains[$requesterIdx % \count($projectDomains)]))
                ->setProposedSourceUrl(sprintf('https://%s/blog/partenaire', $projectDomains[$publisherIdx % \count($projectDomains)]));

            // Lier au backlink correspondant si PLACED
            if (BacklinkStatus::PLACED === $statuses[$i]) {
                $backlink = $this->getReference(FixtureReference::backlink($i), Backlink::class);
                $exchange->setBacklink($backlink);
                $exchange->setCompletedAt(new \DateTimeImmutable(sprintf('-%d days', random_int(1, 30))));
            }

            if (\in_array($statuses[$i], [BacklinkStatus::ACCEPTED, BacklinkStatus::PLACED], true)) {
                $exchange->setAcceptedAt(new \DateTimeImmutable(sprintf('-%d days', random_int(5, 45))));
            }

            $manager->persist($exchange);
        }

        $manager->flush();
    }
}
