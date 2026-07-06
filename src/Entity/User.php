<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Traits\TimestampableTrait;
use App\Entity\Traits\UuidPrimaryKeyTrait;
use App\Enum\ProjectGuestAccess;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    normalizationContext: ['groups' => ['api:read', 'user:read']],
    operations: [
        new GetCollection(),
        new Get(),
    ],
)]
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'uniq_users_email', columns: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'This email is already used.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use TimestampableTrait;
    use UuidPrimaryKeyTrait;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 255)]
    #[Groups(['user:read'])]
    private string $email = '';

    #[ORM\Column(name: 'password_hash', length: 255)]
    #[Ignore]
    private string $passwordHash = '';

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $firstName = '';

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $lastName = '';

    #[ORM\Column(enumType: UserRole::class)]
    #[Groups(['user:read'])]
    private UserRole $role = UserRole::VIEWER;

    #[ORM\Column]
    private bool $isVerified = false;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $emailVerificationTokenHash = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $emailVerificationTokenExpiresAt = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $passwordResetTokenHash = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $passwordResetTokenExpiresAt = null;

    #[ORM\Column(name: 'is_2fa_enabled')]
    #[Ignore]
    private bool $is2faEnabled = false;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    #[Groups(['user:read'])]
    private ?\DateTimeImmutable $lastLoginAt = null;

    #[ORM\Column(length: 10, options: ['default' => 'fr'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 10)]
    #[Groups(['user:read'])]
    private string $locale = 'fr';

    /** @var Collection<int, OrganizationUser> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: OrganizationUser::class, orphanRemoval: true)]
    private Collection $organizationUsers;

    /** @var Collection<int, Project> */
    #[ORM\OneToMany(mappedBy: 'owner', targetEntity: Project::class)]
    private Collection $ownedProjects;

    /** @var Collection<int, Project> */
    #[ORM\ManyToMany(targetEntity: Project::class, mappedBy: 'members')]
    private Collection $memberProjects;



    /** @var Collection<int, ProjectGuest> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: ProjectGuest::class, orphanRemoval: true)]
    private Collection $projectGuestMemberships;

    /** @var Collection<int, AuditLog> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: AuditLog::class)]
    private Collection $auditLogs;

    /** @var Collection<int, RateLimitEvent> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: RateLimitEvent::class)]
    private Collection $rateLimitEvents;

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->organizationUsers = new ArrayCollection();
        $this->ownedProjects = new ArrayCollection();
        $this->memberProjects = new ArrayCollection();
        $this->projectGuestMemberships = new ArrayCollection();
        $this->auditLogs = new ArrayCollection();
        $this->rateLimitEvents = new ArrayCollection();
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = strtolower(trim($email));

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = trim($firstName);

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = trim($lastName);

        return $this;
    }

    public function getDisplayName(): string
    {
        $displayName = trim(sprintf('%s %s', $this->firstName, $this->lastName));

        return '' === $displayName ? $this->email : $displayName;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        $roles = match ($this->role) {
            UserRole::ADMIN => ['ROLE_ADMIN'],
            UserRole::OWNER, UserRole::EDITOR => ['ROLE_MANAGER'],
            UserRole::VIEWER => ['ROLE_USER'],
        };
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    public function getRole(): UserRole
    {
        return $this->role;
    }

    public function setRole(UserRole $role): self
    {
        $this->role = $role;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->passwordHash;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(string $passwordHash): self
    {
        $this->passwordHash = $passwordHash;

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): self
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function getEmailVerificationTokenHash(): ?string
    {
        return $this->emailVerificationTokenHash;
    }

    public function setEmailVerificationTokenHash(?string $emailVerificationTokenHash): self
    {
        $this->emailVerificationTokenHash = $emailVerificationTokenHash;

        return $this;
    }

    public function getEmailVerificationTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->emailVerificationTokenExpiresAt;
    }

    public function setEmailVerificationTokenExpiresAt(?\DateTimeImmutable $emailVerificationTokenExpiresAt): self
    {
        $this->emailVerificationTokenExpiresAt = $emailVerificationTokenExpiresAt;

        return $this;
    }

    public function clearEmailVerificationToken(): self
    {
        $this->emailVerificationTokenHash = null;
        $this->emailVerificationTokenExpiresAt = null;

        return $this;
    }

    public function getPasswordResetTokenHash(): ?string
    {
        return $this->passwordResetTokenHash;
    }

    public function setPasswordResetTokenHash(?string $passwordResetTokenHash): self
    {
        $this->passwordResetTokenHash = $passwordResetTokenHash;

        return $this;
    }

    public function getPasswordResetTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->passwordResetTokenExpiresAt;
    }

    public function setPasswordResetTokenExpiresAt(?\DateTimeImmutable $passwordResetTokenExpiresAt): self
    {
        $this->passwordResetTokenExpiresAt = $passwordResetTokenExpiresAt;

        return $this;
    }

    public function clearPasswordResetToken(): self
    {
        $this->passwordResetTokenHash = null;
        $this->passwordResetTokenExpiresAt = null;

        return $this;
    }

    public function eraseCredentials(): void {}

    public function is2faEnabled(): bool
    {
        return $this->is2faEnabled;
    }

    public function setIs2faEnabled(bool $is2faEnabled): self
    {
        $this->is2faEnabled = $is2faEnabled;

        return $this;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeImmutable $lastLoginAt): self
    {
        $this->lastLoginAt = $lastLoginAt;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): self
    {
        $this->locale = strtolower(trim($locale));

        return $this;
    }

    /** @return Collection<int, OrganizationUser> */
    public function getOrganizationUsers(): Collection
    {
        return $this->organizationUsers;
    }

    /** @return Collection<int, Project> */
    public function getOwnedProjects(): Collection
    {
        return $this->ownedProjects;
    }

    public function addOrganizationUser(OrganizationUser $organizationUser): static
    {
        if (!$this->organizationUsers->contains($organizationUser)) {
            $this->organizationUsers->add($organizationUser);
            $organizationUser->setUser($this);
        }

        return $this;
    }

    public function removeOrganizationUser(OrganizationUser $organizationUser): static
    {
        if ($this->organizationUsers->removeElement($organizationUser)) {
            // set the owning side to null (unless already changed)
            if ($organizationUser->getUser() === $this) {
                $organizationUser->setUser(null);
            }
        }

        return $this;
    }

    public function addOwnedProject(Project $ownedProject): static
    {
        if (!$this->ownedProjects->contains($ownedProject)) {
            $this->ownedProjects->add($ownedProject);
            $ownedProject->setOwner($this);
        }

        return $this;
    }

    public function removeOwnedProject(Project $ownedProject): static
    {
        if ($this->ownedProjects->removeElement($ownedProject)) {
            // set the owning side to null (unless already changed)
            if ($ownedProject->getOwner() === $this) {
                $ownedProject->setOwner(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, AuditLog>
     */
    public function getAuditLogs(): Collection
    {
        return $this->auditLogs;
    }

    public function addAuditLog(AuditLog $auditLog): static
    {
        if (!$this->auditLogs->contains($auditLog)) {
            $this->auditLogs->add($auditLog);
            $auditLog->setUser($this);
        }

        return $this;
    }

    public function removeAuditLog(AuditLog $auditLog): static
    {
        if ($this->auditLogs->removeElement($auditLog)) {
            // set the owning side to null (unless already changed)
            if ($auditLog->getUser() === $this) {
                $auditLog->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, RateLimitEvent>
     */
    public function getRateLimitEvents(): Collection
    {
        return $this->rateLimitEvents;
    }

    public function addRateLimitEvent(RateLimitEvent $rateLimitEvent): static
    {
        if (!$this->rateLimitEvents->contains($rateLimitEvent)) {
            $this->rateLimitEvents->add($rateLimitEvent);
            $rateLimitEvent->setUser($this);
        }

        return $this;
    }

    public function removeRateLimitEvent(RateLimitEvent $rateLimitEvent): static
    {
        if ($this->rateLimitEvents->removeElement($rateLimitEvent)) {
            // set the owning side to null (unless already changed)
            if ($rateLimitEvent->getUser() === $this) {
                $rateLimitEvent->setUser(null);
            }
        }

        return $this;
    }

    /** @return Collection<int, ProjectGuest> */
    public function getProjectGuestMemberships(): Collection
    {
        return $this->projectGuestMemberships;
    }

    /** @return Collection<int, Project> */
    public function getGuestProjects(): Collection
    {
        $projects = [];
        foreach ($this->projectGuestMemberships as $membership) {
            $project = $membership->getProject();
            if (null !== $project) {
                $projects[] = $project;
            }
        }

        return new ArrayCollection($projects);
    }

    public function addGuestProject(Project $project, ProjectGuestAccess $access = ProjectGuestAccess::CONTENT): self
    {
        $project->addGuest($this, $access);

        return $this;
    }

    public function removeGuestProject(Project $project): self
    {
        $project->removeGuest($this);

        return $this;
    }

    /** @return Collection<int, Project> */
    public function getMemberProjects(): Collection
    {
        return $this->memberProjects;
    }

    public function addMemberProject(Project $project): self
    {
        if (!$this->memberProjects->contains($project)) {
            $this->memberProjects->add($project);
            $project->addMember($this);
        }

        return $this;
    }

    public function removeMemberProject(Project $project): self
    {
        if ($this->memberProjects->removeElement($project)) {
            $project->removeMember($this);
        }

        return $this;
    }
}
