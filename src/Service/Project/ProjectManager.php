<?php

declare(strict_types=1);

namespace App\Service\Project;

use App\Entity\Domain;
use App\Entity\Organization;
use App\Entity\OrganizationUser;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\ProjectStatus;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;

final class ProjectManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProjectWebsiteUrlNormalizer $websiteUrlNormalizer,
    ) {}

    public function createForUser(Project $project, User $owner, string $websiteUrl): void
    {
        $normalizedWebsiteUrl = $this->websiteUrlNormalizer->requireValid($websiteUrl);
        $organization = $this->resolveWritableOrganization($owner);

        $project
            ->setOwner($owner)
            ->setStatus(ProjectStatus::ACTIVE)
            ->addMember($owner);
        $organization->addProject($project);

        $this->syncPrimaryDomain($project, $normalizedWebsiteUrl);

        $this->entityManager->persist($project);
        $this->entityManager->flush();
    }

    public function update(Project $project, string $websiteUrl): void
    {
        $normalizedWebsiteUrl = $this->websiteUrlNormalizer->requireValid($websiteUrl);
        $this->syncPrimaryDomain($project, $normalizedWebsiteUrl);
        $project->touch();

        $this->entityManager->flush();
    }

    public function archive(Project $project): void
    {
        $project
            ->setStatus(ProjectStatus::ARCHIVED)
            ->touch();

        $this->entityManager->flush();
    }

    public function getPrimaryDomain(Project $project): ?Domain
    {
        $domain = $project->getDomains()->first();

        return $domain instanceof Domain ? $domain : null;
    }

    public function getPrimaryWebsiteUrl(Project $project): ?string
    {
        return $this->getPrimaryDomain($project)?->getRootDomain();
    }

    private function syncPrimaryDomain(Project $project, string $normalizedWebsiteUrl): Domain
    {
        $domain = $this->getPrimaryDomain($project);
        if (!$domain instanceof Domain) {
            $domain = new Domain();
            $project->addDomain($domain);
            $this->entityManager->persist($domain);
        }

        $domain
            ->setRootDomain($normalizedWebsiteUrl)
            ->setVerifiedAt(null)
            ->setVerificationMethod(null)
            ->touch();

        return $domain;
    }

    private function resolveWritableOrganization(User $owner): Organization
    {
        foreach ($owner->getOrganizationUsers() as $organizationUser) {
            $organization = $organizationUser->getOrganization();
            if (
                $organization instanceof Organization
                && in_array($organizationUser->getRole(), [UserRole::OWNER, UserRole::ADMIN, UserRole::EDITOR], true)
            ) {
                return $organization;
            }
        }

        $organization = new Organization();
        $organization
            ->setName($owner->getDisplayName() . ' workspace')
            ->setBillingEmail($owner->getEmail());

        $organizationUser = new OrganizationUser();
        $organizationUser->setRole(UserRole::OWNER);
        $organization->addOrganizationUser($organizationUser);
        $owner->addOrganizationUser($organizationUser);

        $this->entityManager->persist($organization);
        $this->entityManager->persist($organizationUser);

        return $organization;
    }
}
