<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Audit;
use App\Entity\Domain;
use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\UserRole;
use App\Exception\AnalysisLimitExceededException;
use App\Service\Billing\AnalysisQuotaManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AnalysisQuotaTest extends KernelTestCase
{
    public function testFreeLimitAppliesToAccountAndHashedIp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $quotaManager = $container->get(AnalysisQuotaManager::class);
        $ipAddress = '198.51.100.' . random_int(1, 240);

        [$firstUser, $firstProject, $firstDomain] = $this->createProjectGraph($entityManager, 'first-' . bin2hex(random_bytes(4)) . '@example.com');

        for ($index = 0; $index < AnalysisQuotaManager::FREE_ANALYSIS_LIMIT; ++$index) {
            $audit = (new Audit())
                ->setProject($firstProject)
                ->setDomain($firstDomain)
                ->setRequestedBy($firstUser);
            $entityManager->persist($audit);
            $quotaManager->reserve($audit, $firstUser, $ipAddress);
            $entityManager->flush();
        }

        $blockedAccountAudit = (new Audit())
            ->setProject($firstProject)
            ->setDomain($firstDomain)
            ->setRequestedBy($firstUser);
        $entityManager->persist($blockedAccountAudit);

        $accountBlocked = false;
        try {
            $quotaManager->reserve($blockedAccountAudit, $firstUser, '203.0.113.22');
        } catch (AnalysisLimitExceededException) {
            $accountBlocked = true;
        }
        self::assertTrue($accountBlocked, 'The fourth free analysis should be blocked for the account.');

        [$secondUser, $secondProject, $secondDomain] = $this->createProjectGraph($entityManager, 'second-' . bin2hex(random_bytes(4)) . '@example.com');
        $blockedIpAudit = (new Audit())
            ->setProject($secondProject)
            ->setDomain($secondDomain)
            ->setRequestedBy($secondUser);
        $entityManager->persist($blockedIpAudit);

        $this->expectException(AnalysisLimitExceededException::class);
        $quotaManager->reserve($blockedIpAudit, $secondUser, $ipAddress);
    }

    /** @return array{User, Project, Domain} */
    private function createProjectGraph(EntityManagerInterface $entityManager, string $email): array
    {
        $user = (new User())
            ->setEmail($email)
            ->setFirstName('Quota')
            ->setLastName('Test')
            ->setPasswordHash('not-used')
            ->setRole(UserRole::VIEWER)
            ->setIsVerified(true);
        $organization = (new Organization())
            ->setName('Quota test ' . bin2hex(random_bytes(4)))
            ->setBillingEmail($email);
        $project = (new Project())
            ->setName('Quota project ' . bin2hex(random_bytes(4)))
            ->setOwner($user);
        $organization->addProject($project);
        $domain = (new Domain())->setRootDomain('https://example.com/');
        $project->addDomain($domain);

        $entityManager->persist($user);
        $entityManager->persist($organization);
        $entityManager->persist($project);
        $entityManager->persist($domain);
        $entityManager->flush();

        return [$user, $project, $domain];
    }
}
