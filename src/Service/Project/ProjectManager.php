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
use App\Service\Language\LanguageDetectionService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class ProjectManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProjectWebsiteUrlNormalizer $websiteUrlNormalizer,
        private readonly LanguageDetectionService $languageDetector,
        private readonly LoggerInterface $logger,
    ) {}

    public function createForUser(Project $project, User $owner, string $websiteUrl): void
    {
        $normalizedWebsiteUrl = $this->websiteUrlNormalizer->requireValid($websiteUrl);
        $organization = $this->resolveWritableOrganization($owner);

        $project
            ->setOwner($owner)
            ->setStatus(ProjectStatus::ACTIVE);
        $organization->addProject($project);

        $this->syncPrimaryDomain($project, $normalizedWebsiteUrl);
        $this->autoDetectLanguageIfNeeded($project, $normalizedWebsiteUrl);

        $this->entityManager->persist($project);
        $this->entityManager->flush();
    }

    public function createManaged(Project $project, string $websiteUrl): void
    {
        if (null === $project->getOrganization()) {
            throw new \LogicException('An organization is required for an admin-created project.');
        }

        $normalizedWebsiteUrl = $this->websiteUrlNormalizer->requireValid($websiteUrl);
        $this->syncPrimaryDomain($project, $normalizedWebsiteUrl);
        $this->autoDetectLanguageIfNeeded($project, $normalizedWebsiteUrl);

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

    public function delete(Project $project): void
    {
        $this->entityManager->remove($project);
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

    /**
     * Runs automatic language detection on the given URL and pre-fills
     * the project's language, country, and confidence fields.
     *
     * Only runs when:
     *   - autoDetectLanguage is enabled on the project
     *   - the project does not already have a language set
     */
    private function autoDetectLanguageIfNeeded(Project $project, string $websiteUrl): void
    {
        if (!$project->isAutoDetectLanguage()) {
            return;
        }

        // Don't overwrite user-provided values
        if (null !== $project->getLanguage()) {
            return;
        }

        try {
            $result = $this->languageDetector->detect($websiteUrl);

            if ($result->isConfident() && null !== $result->language) {
                $project->setLanguage($result->language);
                $project->setLanguageConfidence($result->confidence);

                if (null !== $result->country && null === $project->getTargetCountry()) {
                    $project->setTargetCountry($result->country);
                }
            }

            $this->logger->info('Language auto-detection completed for project.', [
                'project' => $project->getName(),
                'url' => $websiteUrl,
                'result' => $result->toArray(),
            ]);
        } catch (\Throwable $exception) {
            $this->logger->warning('Language auto-detection failed for project.', [
                'project' => $project->getName(),
                'url' => $websiteUrl,
                'error' => $exception->getMessage(),
            ]);
        }
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
