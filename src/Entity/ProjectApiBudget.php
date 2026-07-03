<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TimestampableTrait;
use App\Entity\Traits\UuidPrimaryKeyTrait;
use App\Repository\ProjectApiBudgetRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProjectApiBudgetRepository::class)]
#[ORM\Table(name: 'project_api_budgets')]
#[ORM\UniqueConstraint(name: 'uniq_project_api_budgets_project', columns: ['project_id'])]
class ProjectApiBudget
{
    use TimestampableTrait;
    use UuidPrimaryKeyTrait;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4)]
    private string $dailyMaxCost = '5.0000';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4)]
    private string $monthlyMaxCost = '100.0000';

    #[ORM\Column]
    private int $dailyMaxAiTokens = 100000;

    #[ORM\Column]
    private int $dailyMaxSerpCalls = 50;

    #[ORM\Column(type: 'smallint')]
    private int $alertThreshold = 80;

    public function __construct()
    {
        $this->initializeUuid();
        $this->initializeTimestamps();
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

    public function getDailyMaxCost(): string
    {
        return $this->dailyMaxCost;
    }

    public function setDailyMaxCost(string $dailyMaxCost): self
    {
        $this->dailyMaxCost = $dailyMaxCost;

        return $this;
    }

    public function getMonthlyMaxCost(): string
    {
        return $this->monthlyMaxCost;
    }

    public function setMonthlyMaxCost(string $monthlyMaxCost): self
    {
        $this->monthlyMaxCost = $monthlyMaxCost;

        return $this;
    }

    public function getDailyMaxAiTokens(): int
    {
        return $this->dailyMaxAiTokens;
    }

    public function setDailyMaxAiTokens(int $dailyMaxAiTokens): self
    {
        $this->dailyMaxAiTokens = max(0, $dailyMaxAiTokens);

        return $this;
    }

    public function getDailyMaxSerpCalls(): int
    {
        return $this->dailyMaxSerpCalls;
    }

    public function setDailyMaxSerpCalls(int $dailyMaxSerpCalls): self
    {
        $this->dailyMaxSerpCalls = max(0, $dailyMaxSerpCalls);

        return $this;
    }

    public function getAlertThreshold(): int
    {
        return $this->alertThreshold;
    }

    public function setAlertThreshold(int $alertThreshold): self
    {
        $this->alertThreshold = max(0, min(100, $alertThreshold));

        return $this;
    }
}
