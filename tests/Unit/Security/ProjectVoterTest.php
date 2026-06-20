<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\Organization;
use App\Entity\OrganizationUser;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\ProjectStatus;
use App\Enum\UserRole;
use App\Security\Voter\ProjectVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchy;

final class ProjectVoterTest extends TestCase
{
    private ProjectVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new ProjectVoter(new RoleHierarchy([
            'ROLE_MANAGER' => ['ROLE_USER'],
            'ROLE_ADMIN' => ['ROLE_MANAGER'],
        ]));
    }

    public function testOwnerCanManageActiveProject(): void
    {
        $owner = $this->user(UserRole::VIEWER);
        $project = $this->project(owner: $owner);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($owner, $project, ProjectVoter::VIEW));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($owner, $project, ProjectVoter::EDIT));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($owner, $project, ProjectVoter::CRAWL));
    }

    public function testOrganizationEditorCanManageOrganizationProject(): void
    {
        $editor = $this->user(UserRole::VIEWER);
        $organization = new Organization();
        $organizationUser = new OrganizationUser();
        $organizationUser
            ->setUser($editor)
            ->setRole(UserRole::EDITOR);
        $organization->addOrganizationUser($organizationUser);

        $project = $this->project(organization: $organization);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($editor, $project, ProjectVoter::VIEW));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($editor, $project, ProjectVoter::MANAGE));
    }

    public function testGuestPermissions(): void
    {
        $guest = $this->user(UserRole::VIEWER);
        $project = $this->project();
        $project->addGuest($guest);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($guest, $project, ProjectVoter::VIEW));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($guest, $project, ProjectVoter::MANAGE_CONTENT));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($guest, $project, ProjectVoter::EDIT));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($guest, $project, ProjectVoter::LAUNCH_AUDIT));
    }

    private function vote(User $user, Project $project, string $attribute): int
    {
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());

        return $this->voter->vote($token, $project, [$attribute]);
    }

    private function user(UserRole $role): User
    {
        $user = new User();
        $user
            ->setEmail(sprintf('%s-%s@example.com', strtolower($role->value), $user->getId()))
            ->setFirstName('Demo')
            ->setLastName($role->value)
            ->setRole($role)
            ->setIsVerified(true)
            ->setPasswordHash('hash');

        return $user;
    }

    private function project(
        ?User $owner = null,
        ProjectStatus $status = ProjectStatus::ACTIVE,
        ?Organization $organization = null,
    ): Project {
        $project = new Project();
        $project
            ->setName('Demo project')
            ->setStatus($status);

        if (null !== $owner) {
            $project->setOwner($owner);
        }

        if (null !== $organization) {
            $organization->addProject($project);
        }

        return $project;
    }
}
