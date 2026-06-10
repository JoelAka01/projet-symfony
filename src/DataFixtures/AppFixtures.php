<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Domain;
use App\Entity\Organization;
use App\Entity\OrganizationUser;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\ProjectStatus;
use App\Enum\UserRole;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AppFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher) {}

    public function load(ObjectManager $manager): void
    {
        $admin = $this->createUser($manager, 'admin@example.com', 'Admin', 'Demo', UserRole::ADMIN);
        $managerUser = $this->createUser($manager, 'manager@example.com', 'Manager', 'Demo', UserRole::EDITOR);
        $user = $this->createUser($manager, 'user@example.com', 'User', 'Demo', UserRole::VIEWER);

        $agency = $this->createOrganization($manager, 'Demo SEO Agency', 'admin@example.com');
        $this->addOrganizationUser($manager, $agency, $admin, UserRole::OWNER);
        $this->addOrganizationUser($manager, $agency, $managerUser, UserRole::EDITOR);
        $this->addOrganizationUser($manager, $agency, $user, UserRole::VIEWER);

        $this->createProject(
            $manager,
            $agency,
            $managerUser,
            'Symfony official website',
            'https://symfony.com/',
            ProjectStatus::ACTIVE,
            [$admin, $user],
        );

        $personalOrganization = $this->createOrganization($manager, 'User Demo Workspace', 'user@example.com');
        $this->addOrganizationUser($manager, $personalOrganization, $user, UserRole::OWNER);
        $this->createProject(
            $manager,
            $personalOrganization,
            $user,
            'Example public website',
            'https://example.com/',
        );

        $manager->flush();
    }

    private function createUser(
        ObjectManager $manager,
        string $email,
        string $firstName,
        string $lastName,
        UserRole $role,
    ): User {
        $user = new User();
        $user
            ->setEmail($email)
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setRole($role)
            ->setIsVerified(true);

        $user->setPasswordHash($this->passwordHasher->hashPassword($user, 'password'));

        $manager->persist($user);

        return $user;
    }

    private function createOrganization(ObjectManager $manager, string $name, string $billingEmail): Organization
    {
        $organization = new Organization();
        $organization
            ->setName($name)
            ->setBillingEmail($billingEmail);

        $manager->persist($organization);

        return $organization;
    }

    private function addOrganizationUser(
        ObjectManager $manager,
        Organization $organization,
        User $user,
        UserRole $role,
    ): void {
        $organizationUser = new OrganizationUser();
        $organizationUser->setRole($role);
        $organization->addOrganizationUser($organizationUser);
        $user->addOrganizationUser($organizationUser);

        $manager->persist($organizationUser);
    }

    /**
     * @param list<User> $members
     */
    private function createProject(
        ObjectManager $manager,
        Organization $organization,
        User $owner,
        string $name,
        string $websiteUrl,
        ProjectStatus $status = ProjectStatus::ACTIVE,
        array $members = [],
    ): void {
        $project = new Project();
        $project
            ->setOwner($owner)
            ->setName($name)
            ->setStatus($status)
            ->setDefaultLanguage('en')
            ->setTargetCountry('US')
            ->addMember($owner);
        $organization->addProject($project);

        foreach ($members as $member) {
            $project->addMember($member);
        }

        $domain = new Domain();
        $domain->setRootDomain($websiteUrl);
        $project->addDomain($domain);

        $manager->persist($project);
        $manager->persist($domain);
    }
}
