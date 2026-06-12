<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260612180001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align AI usage and audit requester index names with Doctrine metadata.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER INDEX idx_ai_usages_user RENAME TO IDX_4D31574DA76ED395');
        $this->addSql('ALTER INDEX idx_ai_usages_project RENAME TO IDX_4D31574D166D1F9C');
        $this->addSql('ALTER INDEX idx_audits_requested_by RENAME TO IDX_32451E6C4DA1E751');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER INDEX IDX_4D31574DA76ED395 RENAME TO idx_ai_usages_user');
        $this->addSql('ALTER INDEX IDX_4D31574D166D1F9C RENAME TO idx_ai_usages_project');
        $this->addSql('ALTER INDEX IDX_32451E6C4DA1E751 RENAME TO idx_audits_requested_by');
    }
}
