<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Project;
use App\Entity\User;
use App\Enum\ProjectStatus;
use App\Enum\UserRole;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * @extends Voter<string, Project>
 */
final class ProjectVoter extends Voter
{
    public const VIEW = 'PROJECT_VIEW';
    public const EDIT = 'PROJECT_EDIT';
    public const MANAGE = 'PROJECT_MANAGE';
    public const MANAGE_CONTENT = 'PROJECT_MANAGE_CONTENT';
    public const CRAWL = 'PROJECT_CRAWL';
    public const LAUNCH_AUDIT = 'LAUNCH_AUDIT';
    public const DELETE = 'PROJECT_DELETE';

    private const ATTRIBUTES = [
        self::VIEW,
        self::EDIT,
        self::MANAGE,
        self::MANAGE_CONTENT,
        self::CRAWL,
        self::LAUNCH_AUDIT,
        self::DELETE,
    ];

    public function __construct(private readonly RoleHierarchyInterface $roleHierarchy) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Project && in_array($attribute, self::ATTRIBUTES, true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if ($this->hasRole($token, 'ROLE_ADMIN')) {
            return true;
        }

        return match ($attribute) {
            self::VIEW => $this->canView($subject, $user),
            self::EDIT, self::MANAGE => $this->canManage($subject, $user, $token),
            self::MANAGE_CONTENT => $this->canManageContent($subject, $user, $token),
            self::CRAWL, self::LAUNCH_AUDIT => ProjectStatus::ACTIVE === $subject->getStatus()
                && $this->canManage($subject, $user, $token),
            self::DELETE => $this->canDelete($subject, $user),
            default => false,
        };
    }

    private function canView(Project $project, User $user): bool
    {
        return $this->isOwner($project, $user)
            || $this->isProjectGuest($project, $user)
            || $this->hasOrganizationMembership($project, $user);
    }

    private function canManage(Project $project, User $user, TokenInterface $token): bool
    {
        return $this->isOwner($project, $user)
            || $this->hasOrganizationRole($project, $user, UserRole::OWNER, UserRole::ADMIN, UserRole::EDITOR);
    }

    private function canManageContent(Project $project, User $user, TokenInterface $token): bool
    {
        return $this->canManage($project, $user, $token)
            || $this->isProjectGuest($project, $user);
    }

    private function isProjectGuest(Project $project, User $user): bool
    {
        foreach ($project->getGuests() as $guest) {
            if ($this->isSameUser($guest, $user)) {
                return true;
            }
        }

        return false;
    }

    private function canDelete(Project $project, User $user): bool
    {
        return $this->isOwner($project, $user)
            || $this->hasOrganizationRole($project, $user, UserRole::OWNER, UserRole::ADMIN);
    }

    private function isOwner(Project $project, User $user): bool
    {
        $owner = $project->getOwner();

        return null !== $owner && $this->isSameUser($owner, $user);
    }

    private function hasOrganizationMembership(Project $project, User $user): bool
    {
        $organization = $project->getOrganization();
        if (null === $organization) {
            return false;
        }

        foreach ($organization->getOrganizationUsers() as $organizationUser) {
            $member = $organizationUser->getUser();
            if (null !== $member && $this->isSameUser($member, $user)) {
                return true;
            }
        }

        return false;
    }

    private function hasOrganizationRole(Project $project, User $user, UserRole ...$roles): bool
    {
        $organization = $project->getOrganization();
        if (null === $organization) {
            return false;
        }

        foreach ($organization->getOrganizationUsers() as $organizationUser) {
            $member = $organizationUser->getUser();
            if (null !== $member && $this->isSameUser($member, $user) && in_array($organizationUser->getRole(), $roles, true)) {
                return true;
            }
        }

        return false;
    }

    private function hasRole(TokenInterface $token, string $role): bool
    {
        return in_array($role, $this->roleHierarchy->getReachableRoleNames($token->getRoleNames()), true);
    }

    private function isSameUser(User $left, User $right): bool
    {
        return $left === $right || $left->getId() === $right->getId();
    }
}
