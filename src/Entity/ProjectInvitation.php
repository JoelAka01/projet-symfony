<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TimestampableTrait;
use App\Entity\Traits\UuidPrimaryKeyTrait;
use App\Enum\ProjectGuestAccess;
use App\Repository\ProjectInvitationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProjectInvitationRepository::class)]
#[ORM\Table(name: 'project_invitations')]
#[ORM\UniqueConstraint(name: 'uniq_project_invitations_token', columns: ['token'])]
class ProjectInvitation
{
    use TimestampableTrait;
    use UuidPrimaryKeyTrait;

    #[ORM\ManyToOne(inversedBy: 'invitations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 255)]
    private string $email = '';

    #[ORM\Column(length: 64)]
    private string $token = '';

    #[ORM\Column(length: 20)]
    private string $status = 'pending'; // 'pending' or 'accepted'

    #[ORM\Column(enumType: ProjectGuestAccess::class)]
    private ProjectGuestAccess $access = ProjectGuestAccess::CONTENT;

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->token = bin2hex(random_bytes(32));
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): self
    {
        $this->project = $project;

        return $this;
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

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): self
    {
        $this->token = $token;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getAccess(): ProjectGuestAccess
    {
        return $this->access;
    }

    public function setAccess(ProjectGuestAccess $access): self
    {
        $this->access = $access;

        return $this;
    }
}
